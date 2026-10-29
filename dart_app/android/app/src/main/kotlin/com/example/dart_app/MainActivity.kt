package com.example.dart_app

import android.app.Activity
import android.content.Context
import android.content.Intent
import android.os.Handler
import android.os.Looper
import android.print.PrintManager
import android.print.PrintAttributes
import android.provider.OpenableColumns
import android.webkit.WebView
import android.webkit.WebViewClient
import java.io.ByteArrayOutputStream
import io.flutter.embedding.android.FlutterActivity
import io.flutter.embedding.engine.FlutterEngine
import io.flutter.plugin.common.MethodChannel

class MainActivity : FlutterActivity() {
    private var fileResult: MethodChannel.Result? = null
    private var documentWebView: WebView? = null
    private val fileRequestCode = 5021
    private val saveRequestCode = 5022
    private var saveResult: MethodChannel.Result? = null
    private var saveBytes: ByteArray? = null

    override fun configureFlutterEngine(flutterEngine: FlutterEngine) {
        super.configureFlutterEngine(flutterEngine)
        MethodChannel(flutterEngine.dartExecutor.binaryMessenger, "phupha/documents")
            .setMethodCallHandler { call, result ->
                if (call.method == "pickFile") {
                    if (fileResult != null) {
                        result.error("BUSY", "กำลังเลือกไฟล์อยู่", null)
                    } else {
                        //ให้ผู้ใช้เลือกเฉพาะไฟล์ที่ต้องการ ไม่ขอสิทธิ์อ่านทั้งเครื่อง
                        val allowPdf = call.argument<Boolean>("allowPdf") == true
                        val intent = Intent(Intent.ACTION_OPEN_DOCUMENT)
                        intent.addCategory(Intent.CATEGORY_OPENABLE)
                        intent.type = if (allowPdf) "*/*" else "image/*"
                        if (allowPdf) {
                            intent.putExtra(Intent.EXTRA_MIME_TYPES, arrayOf("image/*", "application/pdf"))
                        }
                        fileResult = result
                        try {
                            startActivityForResult(intent, fileRequestCode)
                        } catch (error: Exception) {
                            fileResult = null
                            result.error("PICKER", "เปิดตัวเลือกไฟล์ไม่ได้", null)
                        }
                    }
                } else if (call.method == "saveFile") {
                    val bytes = call.argument<ByteArray>("bytes")
                    val name = call.argument<String>("name") ?: "receipt.pdf"
                    if (saveResult != null || bytes == null || bytes.size > 5 * 1024 * 1024) {
                        result.error("SAVE", "ยังบันทึกไฟล์ไม่ได้ กรุณาลองใหม่", null)
                    } else {
                        val intent = Intent(Intent.ACTION_CREATE_DOCUMENT)
                        intent.addCategory(Intent.CATEGORY_OPENABLE)
                        intent.type = call.argument<String>("mime") ?: "application/octet-stream"
                        intent.putExtra(Intent.EXTRA_TITLE, name)
                        saveResult = result
                        saveBytes = bytes
                        try {
                            startActivityForResult(intent, saveRequestCode)
                        } catch (error: Exception) {
                            saveResult = null
                            saveBytes = null
                            result.error("SAVE", "เปิดหน้าบันทึกไฟล์ไม่ได้", null)
                        }
                    }
                } else if (call.method == "printHtml") {
                    val html = call.argument<String>("html")
                    val title = call.argument<String>("title") ?: "Phupha"
                    val baseUrl = call.argument<String>("baseUrl")
                    if (html == null || baseUrl == null) {
                        result.error("DOCUMENT", "ไม่พบเอกสาร", null)
                    } else {
                        printHtml(html, title, baseUrl, call.argument<Boolean>("landscape") == true, result)
                    }
                } else {
                    result.notImplemented()
                }
            }
    }

    private fun printHtml(html: String, title: String, baseUrl: String, landscape: Boolean, result: MethodChannel.Result) {
        documentWebView?.destroy()
        val webView = WebView(this)
        documentWebView = webView
        //โหลด HTML จาก API ที่ตรวจ session แล้ว ไม่ให้หน้าเอกสารรัน JavaScript เพิ่ม
        webView.settings.javaScriptEnabled = false
        webView.settings.allowFileAccess = false
        webView.settings.allowContentAccess = false
        var answered = false
        val handler = Handler(Looper.getMainLooper())
        val timeout = Runnable {
            if (!answered) {
                answered = true
                result.error("TIMEOUT", "โหลดเอกสารนานเกินไป กรุณาลองใหม่", null)
            }
        }
        handler.postDelayed(timeout, 20000)
        webView.webViewClient = object : WebViewClient() {
            override fun onPageFinished(view: WebView, url: String) {
                if (answered) return
                answered = true
                handler.removeCallbacks(timeout)
                try {
                    //Android มีตัวเลือก Save as PDF ในหน้าพิมพ์ ผู้ใช้เลือกที่บันทึกเองได้
                    val manager = getSystemService(Context.PRINT_SERVICE) as PrintManager
                    val paper = if (landscape) PrintAttributes.MediaSize.ISO_A4.asLandscape() else PrintAttributes.MediaSize.ISO_A4
                    val attributes = PrintAttributes.Builder().setMediaSize(paper).build()
                    manager.print(title, view.createPrintDocumentAdapter(title), attributes)
                    result.success(null)
                } catch (error: Exception) {
                    result.error("PRINT", "เปิดหน้าพิมพ์ไม่ได้", null)
                }
            }
        }
        webView.loadDataWithBaseURL(baseUrl, html, "text/html", "UTF-8", null)
    }

    @Deprecated("Android เรียกกลับหลังผู้ใช้เลือกไฟล์")
    override fun onActivityResult(requestCode: Int, resultCode: Int, data: Intent?) {
        super.onActivityResult(requestCode, resultCode, data)
        //บันทึกลง URI ที่ผู้ใช้เลือก จึงไม่ต้องขอสิทธิ์จัดการไฟล์ทั้งเครื่อง
        if (requestCode == saveRequestCode) {
            val result = saveResult ?: return
            val bytes = saveBytes
            saveResult = null
            saveBytes = null
            val uri = data?.data
            if (resultCode != Activity.RESULT_OK || uri == null || bytes == null) {
                result.success(false)
                return
            }
            try {
                val output = contentResolver.openOutputStream(uri) ?: throw Exception("เปิดไฟล์ไม่ได้")
                output.use { it.write(bytes) }
                result.success(true)
            } catch (error: Exception) {
                result.error("SAVE", "บันทึกไฟล์ไม่ได้", null)
            }
            return
        }
        if (requestCode != fileRequestCode) return
        val result = fileResult ?: return
        fileResult = null
        val uri = data?.data
        if (resultCode != Activity.RESULT_OK || uri == null) {
            result.success(null)
            return
        }
        try {
            var name = "upload"
            contentResolver.query(uri, null, null, null, null)?.use { cursor ->
                if (cursor.moveToFirst()) {
                    val index = cursor.getColumnIndex(OpenableColumns.DISPLAY_NAME)
                    if (index >= 0) name = cursor.getString(index)
                }
            }
            val input = contentResolver.openInputStream(uri) ?: throw Exception("ไม่พบไฟล์")
            //อ่านทีละส่วนเพื่อให้ใช้ได้ตั้งแต่ Android 7 และไม่อ่านไฟล์ใหญ่จนหน่วยความจำเต็ม
            val output = ByteArrayOutputStream()
            input.use { stream ->
                val buffer = ByteArray(8192)
                var count = stream.read(buffer)
                while (count != -1 && output.size() <= 5 * 1024 * 1024) {
                    output.write(buffer, 0, count)
                    count = stream.read(buffer)
                }
            }
            val bytes = output.toByteArray()
            if (bytes.size > 5 * 1024 * 1024) {
                result.error("SIZE", "กรุณาเลือกไฟล์ไม่เกิน 5 MB", null)
            } else {
                result.success(mapOf("name" to name, "bytes" to bytes))
            }
        } catch (error: Exception) {
            result.error("FILE", "อ่านไฟล์ที่เลือกไม่ได้", null)
        }
    }

    override fun onDestroy() {
        documentWebView?.destroy()
        fileResult?.error("CLOSED", "ปิดหน้าจอแล้ว", null)
        fileResult = null
        saveResult?.error("CLOSED", "ปิดหน้าจอแล้ว", null)
        saveResult = null
        saveBytes = null
        super.onDestroy()
    }
}
