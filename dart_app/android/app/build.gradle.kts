import java.util.Properties

plugins {
    id("com.android.application")
    id("kotlin-android")
    // The Flutter Gradle Plugin must be applied after the Android and Kotlin Gradle plugins.
    id("dev.flutter.flutter-gradle-plugin")
}

//อ่านกุญแจ release ของเจ้าของโครงการเมื่อมีไฟล์นี้ โดยไม่ใส่รหัสผ่านไว้ในซอร์ส
val releaseKeyFile = rootProject.file("key.properties")
val releaseKey = Properties()
if (releaseKeyFile.exists()) {
    releaseKeyFile.inputStream().use { releaseKey.load(it) }
    for (name in listOf("storeFile", "storePassword", "keyAlias", "keyPassword")) {
        require(!releaseKey.getProperty(name).isNullOrBlank()) {
            "กรอก $name ใน android/key.properties ให้ครบ"
        }
    }
}

android {
    namespace = "com.example.dart_app"
    compileSdk = flutter.compileSdkVersion
    ndkVersion = flutter.ndkVersion

    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_11
        targetCompatibility = JavaVersion.VERSION_11
    }

    kotlinOptions {
        jvmTarget = JavaVersion.VERSION_11.toString()
    }

    defaultConfig {
        //ใช้รหัสเฉพาะของโครงการแทน com.example เพื่อไม่ให้ชนกับแอปตัวอย่างอื่น
        applicationId = "io.github.sarayootphamornwijityuth.phupha"
        // You can update the following values to match your application needs.
        // For more information, see: https://flutter.dev/to/review-gradle-config.
        minSdk = flutter.minSdkVersion
        targetSdk = flutter.targetSdkVersion
        versionCode = flutter.versionCode
        versionName = flutter.versionName
    }

    signingConfigs {
        if (releaseKeyFile.exists()) {
            create("release") {
                storeFile = file(releaseKey.getProperty("storeFile"))
                storePassword = releaseKey.getProperty("storePassword")
                keyAlias = releaseKey.getProperty("keyAlias")
                keyPassword = releaseKey.getProperty("keyPassword")
            }
        }
    }

    buildTypes {
        release {
            //ยังไม่มี release key ใช้ debug key เดิมเพื่อติดตั้งทดสอบเท่านั้น
            signingConfig = if (releaseKeyFile.exists()) {
                signingConfigs.getByName("release")
            } else {
                signingConfigs.getByName("debug")
            }
        }
    }
}

flutter {
    source = "../.."
}
