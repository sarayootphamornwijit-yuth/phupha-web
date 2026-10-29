import Flutter
import UIKit
import WebKit
import MobileCoreServices

@main
@objc class AppDelegate: FlutterAppDelegate, UIDocumentPickerDelegate, WKNavigationDelegate {
  private var fileResult: FlutterResult?
  private var printResult: FlutterResult?
  private var documentWebView: WKWebView?
  private var documentTitle = "Phupha"
  private var documentLandscape = false

  override func application(
    _ application: UIApplication,
    didFinishLaunchingWithOptions launchOptions: [UIApplication.LaunchOptionsKey: Any]?
  ) -> Bool {
    GeneratedPluginRegistrant.register(with: self)
    if let controller = window?.rootViewController as? FlutterViewController {
      let channel = FlutterMethodChannel(name: "phupha/documents", binaryMessenger: controller.binaryMessenger)
      channel.setMethodCallHandler { [weak self] call, result in
        guard let self = self else { return }
        let arguments = call.arguments as? [String: Any] ?? [:]
        if call.method == "pickFile" {
          if self.fileResult != nil {
            result(FlutterError(code: "BUSY", message: "กำลังเลือกไฟล์อยู่", details: nil))
            return
          }
          //เปิด Files ของ iOS เพื่อให้เจ้าของเครื่องเลือกไฟล์เอง
          let allowPdf = arguments["allowPdf"] as? Bool ?? false
          var types = [kUTTypeImage as String]
          if allowPdf { types.append(kUTTypePDF as String) }
          let picker = UIDocumentPickerViewController(documentTypes: types, in: .import)
          picker.delegate = self
          self.fileResult = result
          controller.present(picker, animated: true)
        } else if call.method == "saveFile" {
          guard let typedData = arguments["bytes"] as? FlutterStandardTypedData,
                let name = arguments["name"] as? String,
                typedData.data.count <= 5 * 1024 * 1024 else {
            result(FlutterError(code: "SAVE", message: "ไม่พบไฟล์หรือไฟล์มีขนาดเกิน 5 MB", details: nil))
            return
          }
          do {
            let directory = FileManager.default.temporaryDirectory.appendingPathComponent(UUID().uuidString)
            try FileManager.default.createDirectory(at: directory, withIntermediateDirectories: true, attributes: nil)
            let url = directory.appendingPathComponent((name as NSString).lastPathComponent)
            try typedData.data.write(to: url)
            //หน้าของ iOS ให้เลือก Save to Files หรือส่งไฟล์ต่อได้ตามปกติ
            let share = UIActivityViewController(activityItems: [url], applicationActivities: nil)
            if let popover = share.popoverPresentationController {
              popover.sourceView = controller.view
              popover.sourceRect = CGRect(x: controller.view.bounds.midX, y: controller.view.bounds.midY, width: 1, height: 1)
            }
            share.completionWithItemsHandler = { _, completed, _, _ in
              result(completed)
              try? FileManager.default.removeItem(at: directory)
            }
            controller.present(share, animated: true)
          } catch {
            result(FlutterError(code: "SAVE", message: "เตรียมไฟล์สำหรับบันทึกไม่ได้", details: nil))
          }
        } else if call.method == "printHtml" {
          if self.printResult != nil {
            result(FlutterError(code: "BUSY", message: "กำลังเตรียมเอกสารอยู่", details: nil))
            return
          }
          guard let html = arguments["html"] as? String,
                let address = arguments["baseUrl"] as? String,
                let baseUrl = URL(string: address) else {
            result(FlutterError(code: "DOCUMENT", message: "ไม่พบเอกสาร", details: nil))
            return
          }
          self.printResult = result
          self.documentTitle = arguments["title"] as? String ?? "Phupha"
          self.documentLandscape = arguments["landscape"] as? Bool ?? false
          let configuration = WKWebViewConfiguration()
          configuration.preferences.javaScriptEnabled = false
          let view = WKWebView(frame: CGRect(x: 0, y: 0, width: 595, height: 842), configuration: configuration)
          view.navigationDelegate = self
          self.documentWebView = view
          view.loadHTMLString(html, baseURL: baseUrl)
          DispatchQueue.main.asyncAfter(deadline: .now() + 20) { [weak self, weak view] in
            guard let self = self, self.documentWebView === view, let pending = self.printResult else { return }
            self.printResult = nil
            pending(FlutterError(code: "TIMEOUT", message: "โหลดเอกสารนานเกินไป กรุณาลองใหม่", details: nil))
          }
        } else {
          result(FlutterMethodNotImplemented)
        }
      }
    }
    return super.application(application, didFinishLaunchingWithOptions: launchOptions)
  }

  func documentPicker(_ controller: UIDocumentPickerViewController, didPickDocumentsAt urls: [URL]) {
    guard let result = fileResult else { return }
    fileResult = nil
    guard let url = urls.first else { result(nil); return }
    do {
      let attributes = try FileManager.default.attributesOfItem(atPath: url.path)
      let size = attributes[.size] as? NSNumber
      if (size?.intValue ?? 0) > 5 * 1024 * 1024 {
        result(FlutterError(code: "SIZE", message: "กรุณาเลือกไฟล์ไม่เกิน 5 MB", details: nil))
        return
      }
      let data = try Data(contentsOf: url)
      result(["name": url.lastPathComponent, "bytes": FlutterStandardTypedData(bytes: data)])
    } catch {
      result(FlutterError(code: "FILE", message: "อ่านไฟล์ที่เลือกไม่ได้", details: nil))
    }
  }

  func documentPickerWasCancelled(_ controller: UIDocumentPickerViewController) {
    fileResult?(nil)
    fileResult = nil
  }

  func webView(_ webView: WKWebView, didFinish navigation: WKNavigation!) {
    guard let result = printResult else { return }
    printResult = nil
    //ใช้เอกสารหน้าตาเดียวกับเว็บ แล้วเปิดระบบพิมพ์ของ iOS
    let printer = UIPrintInteractionController.shared
    let information = UIPrintInfo(dictionary: nil)
    information.jobName = documentTitle
    information.outputType = .general
    information.orientation = documentLandscape ? .landscape : .portrait
    printer.printInfo = information
    printer.printFormatter = webView.viewPrintFormatter()
    let presented: Bool
    if UIDevice.current.userInterfaceIdiom == .pad, let root = window?.rootViewController?.view {
      presented = printer.present(from: CGRect(x: root.bounds.midX, y: root.bounds.midY, width: 1, height: 1), in: root, animated: true, completionHandler: nil)
    } else {
      presented = printer.present(animated: true, completionHandler: nil)
    }
    if presented { result(nil) }
    else { result(FlutterError(code: "PRINT", message: "เปิดหน้าพิมพ์ไม่ได้", details: nil)) }
  }

  func webView(_ webView: WKWebView, didFail navigation: WKNavigation!, withError error: Error) {
    printResult?(FlutterError(code: "DOCUMENT", message: "โหลดเอกสารไม่ได้", details: nil))
    printResult = nil
  }
}
