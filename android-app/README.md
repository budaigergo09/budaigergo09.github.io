# PDC Sim 2026 - Android App

A performant Android wrapper for the PDC Darts Simulator web application.

## 🎯 Features

- **Auto-Sync with Website**: Updates automatically when the website changes - no app rebuild needed!
- **Offline Support**: Caches previously loaded content for offline use
- **Native Experience**: Fullscreen, keep screen awake, proper back button handling
- **Optimized WebView**: Hardware acceleration, DOM storage, and aggressive caching
- **Deep Linking**: Opens `budaigergo09.github.io` links directly in the app
- **Splash Screen**: Native Android 12+ splash screen support
- **Small APK Size**: ~3-5 MB thanks to the WebView approach

## 📱 Requirements

- Android Studio Arctic Fox (2020.3.1) or later
- JDK 17+
- Android SDK 34
- Minimum Android version: 7.0 (API 24)

## 🚀 Quick Start

### Option 1: Build with Android Studio (Recommended)

1. Open Android Studio
2. Select **File → Open** and choose the `android-app` folder
3. Wait for Gradle sync to complete
4. Click **Run → Run 'app'** or press `Shift+F10`
5. Select your device/emulator

### Option 2: Build from Command Line

```bash
# Navigate to the android-app directory
cd android-app

# Build debug APK
./gradlew assembleDebug

# The APK will be in: app/build/outputs/apk/debug/app-debug.apk

# Build release APK (requires signing)
./gradlew assembleRelease

# Install on connected device
./gradlew installDebug
```

## 📦 Building Release APK

### 1. Create a Keystore (One-time)

```bash
keytool -genkey -v -keystore pdcsim-release-key.jks -keyalg RSA -keysize 2048 -validity 10000 -alias pdcsim
```

### 2. Create `keystore.properties` (Don't commit this!)

```properties
storePassword=your_store_password
keyPassword=your_key_password
keyAlias=pdcsim
storeFile=../pdcsim-release-key.jks
```

### 3. Update `app/build.gradle.kts`

Add signing config:
```kotlin
android {
    signingConfigs {
        create("release") {
            val keystorePropertiesFile = rootProject.file("keystore.properties")
            val keystoreProperties = java.util.Properties()
            keystoreProperties.load(java.io.FileInputStream(keystorePropertiesFile))
            
            keyAlias = keystoreProperties["keyAlias"] as String
            keyPassword = keystoreProperties["keyPassword"] as String
            storeFile = file(keystoreProperties["storeFile"] as String)
            storePassword = keystoreProperties["storePassword"] as String
        }
    }
    
    buildTypes {
        release {
            signingConfig = signingConfigs.getByName("release")
            // ... rest of config
        }
    }
}
```

### 4. Build Release APK

```bash
./gradlew assembleRelease
```

The signed APK will be at: `app/build/outputs/apk/release/app-release.apk`

## 🔧 Customization

### Change Website URL

Edit `MainActivity.kt` and modify:
```kotlin
private const val WEBSITE_URL = "https://budaigergo09.github.io/darts.html"
```

### Change App Name

Edit `app/src/main/res/values/strings.xml`:
```xml
<string name="app_name">Your App Name</string>
```

### Change App Icon

Replace the vector drawable at:
- `app/src/main/res/drawable/ic_launcher_foreground.xml`
- `app/src/main/res/drawable/ic_launcher_background.xml`

Or use Android Studio's **Image Asset Studio** (right-click res folder → New → Image Asset)

## 📋 Maintenance

The beauty of this WebView approach is **minimal maintenance**:

| Website Update | App Action Required |
|----------------|---------------------|
| Content changes (players, text) | ✅ None - auto-syncs |
| Style changes (CSS) | ✅ None - auto-syncs |
| JavaScript logic | ✅ None - auto-syncs |
| New HTML pages | ✅ None - auto-syncs |
| Audio/Image assets | ✅ None - auto-syncs |
| App icon change | ⚠️ Rebuild app |
| Android permissions | ⚠️ Rebuild app |
| Deep link changes | ⚠️ Rebuild app |

## 🏗️ Project Structure

```
android-app/
├── build.gradle.kts          # Root build file
├── settings.gradle.kts       # Project settings
├── gradle.properties         # Gradle configuration
├── app/
│   ├── build.gradle.kts      # App module build file
│   ├── proguard-rules.pro    # ProGuard rules
│   └── src/main/
│       ├── AndroidManifest.xml
│       ├── java/.../MainActivity.kt   # Main activity
│       └── res/
│           ├── values/       # Strings, colors, themes
│           ├── drawable/     # Vector icons
│           ├── mipmap-*/     # Launcher icons
│           └── xml/          # Backup rules
```

## 🐛 Troubleshooting

### WebView shows blank screen
- Check internet connection
- Clear app data and cache
- Test website in Chrome first

### Audio doesn't play
- Audio requires user interaction first (browser security)
- The app handles this automatically via wake lock

### Back button exits app
- This is intentional when at the first page
- Use the in-app navigation buttons

### App crashes on startup
- Ensure minimum SDK is 24+
- Check Android Studio logcat for errors

## 📄 License

This is part of the PDC Sim 2026 project. 
For personal use only.

## 🔗 Links

- **Website**: https://budaigergo09.github.io/
- **Darts Mode**: https://budaigergo09.github.io/darts.html
- **Season Mode**: https://budaigergo09.github.io/season.html
