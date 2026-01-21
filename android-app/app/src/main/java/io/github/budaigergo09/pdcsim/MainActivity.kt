package io.github.budaigergo09.pdcsim

import android.annotation.SuppressLint
import android.content.Intent
import android.graphics.Bitmap
import android.net.ConnectivityManager
import android.net.NetworkCapabilities
import android.net.Uri
import android.os.Build
import android.os.Bundle
import android.view.KeyEvent
import android.view.View
import android.view.WindowInsets
import android.view.WindowInsetsController
import android.view.WindowManager
import android.webkit.ConsoleMessage
import android.webkit.WebChromeClient
import android.webkit.WebResourceError
import android.webkit.WebResourceRequest
import android.webkit.WebSettings
import android.webkit.WebView
import android.webkit.WebViewClient
import android.widget.FrameLayout
import android.widget.Toast
import androidx.appcompat.app.AppCompatActivity
import androidx.core.splashscreen.SplashScreen.Companion.installSplashScreen
import androidx.webkit.WebSettingsCompat
import androidx.webkit.WebViewFeature

/**
 * PDC Sim 2026 - Android App
 * 
 * This app wraps the web version of PDC Sim for a native Android experience.
 * The web content is loaded from https://budaigergo09.github.io/
 * 
 * Benefits of this approach:
 * - Automatic updates: When you update the website, the app updates too
 * - Offline caching: Previously loaded content works offline
 * - Native features: Wake lock, fullscreen, back button handling
 * - Easy maintenance: No need to rebuild the app for content changes
 */
class MainActivity : AppCompatActivity() {

    companion object {
        // Change this to your GitHub Pages URL
        private const val WEBSITE_URL = "https://budaigergo09.github.io/darts.html"
        private const val SEASON_URL = "https://budaigergo09.github.io/season.html"
        
        // Cache settings
        private const val CACHE_MODE_ONLINE = WebSettings.LOAD_DEFAULT
        private const val CACHE_MODE_OFFLINE = WebSettings.LOAD_CACHE_ELSE_NETWORK
    }

    private lateinit var webView: WebView
    private lateinit var rootLayout: FrameLayout
    private var isLoading = true

    override fun onCreate(savedInstanceState: Bundle?) {
        // Install splash screen before super.onCreate
        val splashScreen = installSplashScreen()
        splashScreen.setKeepOnScreenCondition { isLoading }
        
        super.onCreate(savedInstanceState)
        
        // Setup immersive fullscreen
        setupFullscreen()
        
        // Keep screen on during gameplay
        window.addFlags(WindowManager.LayoutParams.FLAG_KEEP_SCREEN_ON)
        
        // Create layout programmatically for simplicity
        rootLayout = FrameLayout(this).apply {
            setBackgroundColor(0xFF000000.toInt()) // Black background
            layoutParams = FrameLayout.LayoutParams(
                FrameLayout.LayoutParams.MATCH_PARENT,
                FrameLayout.LayoutParams.MATCH_PARENT
            )
        }
        
        webView = WebView(this).apply {
            layoutParams = FrameLayout.LayoutParams(
                FrameLayout.LayoutParams.MATCH_PARENT,
                FrameLayout.LayoutParams.MATCH_PARENT
            )
        }
        
        rootLayout.addView(webView)
        setContentView(rootLayout)
        
        // Configure WebView
        setupWebView()
        
        // Handle deep links
        handleIntent(intent)
        
        // Acquire wake lock
        acquireWakeLock()
    }

    @SuppressLint("SetJavaScriptEnabled")
    private fun setupWebView() {
        webView.apply {
            settings.apply {
                // Essential settings
                javaScriptEnabled = true
                domStorageEnabled = true
                databaseEnabled = true
                
                // Performance optimizations
                cacheMode = if (isNetworkAvailable()) CACHE_MODE_ONLINE else CACHE_MODE_OFFLINE
                
                // Allow file and content access
                allowFileAccess = true
                allowContentAccess = true
                
                // Media settings
                mediaPlaybackRequiresUserGesture = false
                
                // Mixed content for audio files
                mixedContentMode = WebSettings.MIXED_CONTENT_ALWAYS_ALLOW
                
                // Viewport settings
                useWideViewPort = true
                loadWithOverviewMode = true
                
                // Text settings
                minimumFontSize = 1
                defaultFontSize = 16
                
                // Disable zoom for game-like experience
                setSupportZoom(false)
                builtInZoomControls = false
                displayZoomControls = false
            }
            
            // Enable hardware acceleration
            setLayerType(View.LAYER_TYPE_HARDWARE, null)
            
            // Dark theme support for compatible WebViews (API 32+)
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
                if (WebViewFeature.isFeatureSupported(WebViewFeature.ALGORITHMIC_DARKENING)) {
                    WebSettingsCompat.setAlgorithmicDarkeningAllowed(settings, true)
                }
            }
            
            // Set up WebViewClient
            webViewClient = object : WebViewClient() {
                override fun onPageStarted(view: WebView?, url: String?, favicon: Bitmap?) {
                    super.onPageStarted(view, url, favicon)
                    isLoading = true
                }
                
                override fun onPageFinished(view: WebView?, url: String?) {
                    super.onPageFinished(view, url)
                    isLoading = false
                    
                    // Inject CSS to hide any scrollbars and ensure fullscreen
                    view?.evaluateJavascript("""
                        (function() {
                            var style = document.createElement('style');
                            style.innerHTML = `
                                ::-webkit-scrollbar { display: none !important; }
                                * { -webkit-tap-highlight-color: transparent !important; }
                                body { overscroll-behavior: none !important; }
                            `;
                            document.head.appendChild(style);
                        })();
                    """.trimIndent(), null)
                }
                
                override fun onReceivedError(
                    view: WebView?,
                    request: WebResourceRequest?,
                    error: WebResourceError?
                ) {
                    super.onReceivedError(view, request, error)
                    
                    // Only show error for main frame
                    if (request?.isForMainFrame == true) {
                        showOfflineError()
                    }
                }
                
                // Handle external links
                override fun shouldOverrideUrlLoading(
                    view: WebView?,
                    request: WebResourceRequest?
                ): Boolean {
                    val url = request?.url?.toString() ?: return false
                    
                    // Allow internal navigation
                    if (url.contains("budaigergo09.github.io")) {
                        return false
                    }
                    
                    // Open external links in browser
                    try {
                        startActivity(Intent(Intent.ACTION_VIEW, Uri.parse(url)))
                    } catch (e: Exception) {
                        Toast.makeText(this@MainActivity, "Cannot open link", Toast.LENGTH_SHORT).show()
                    }
                    return true
                }
            }
            
            // Set up WebChromeClient for console and audio
            webChromeClient = object : WebChromeClient() {
                override fun onConsoleMessage(consoleMessage: ConsoleMessage?): Boolean {
                    // Forward console messages for debugging
                    consoleMessage?.let {
                        android.util.Log.d("PDCSim", "${it.sourceId()}:${it.lineNumber()}: ${it.message()}")
                    }
                    return true
                }
            }
            
            // Initial load
            loadUrl(WEBSITE_URL)
        }
    }

    private fun setupFullscreen() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.R) {
            window.setDecorFitsSystemWindows(false)
            window.insetsController?.apply {
                hide(WindowInsets.Type.statusBars() or WindowInsets.Type.navigationBars())
                systemBarsBehavior = WindowInsetsController.BEHAVIOR_SHOW_TRANSIENT_BARS_BY_SWIPE
            }
        } else {
            @Suppress("DEPRECATION")
            window.decorView.systemUiVisibility = (
                View.SYSTEM_UI_FLAG_IMMERSIVE_STICKY
                or View.SYSTEM_UI_FLAG_LAYOUT_STABLE
                or View.SYSTEM_UI_FLAG_LAYOUT_HIDE_NAVIGATION
                or View.SYSTEM_UI_FLAG_LAYOUT_FULLSCREEN
                or View.SYSTEM_UI_FLAG_HIDE_NAVIGATION
                or View.SYSTEM_UI_FLAG_FULLSCREEN
            )
        }
    }

    private fun handleIntent(intent: Intent?) {
        intent?.data?.let { uri ->
            val url = uri.toString()
            if (url.isNotEmpty() && ::webView.isInitialized) {
                webView.loadUrl(url)
            }
        }
    }

    override fun onNewIntent(intent: Intent?) {
        super.onNewIntent(intent)
        handleIntent(intent)
    }

    private fun isNetworkAvailable(): Boolean {
        val connectivityManager = getSystemService(CONNECTIVITY_SERVICE) as ConnectivityManager
        val network = connectivityManager.activeNetwork ?: return false
        val capabilities = connectivityManager.getNetworkCapabilities(network) ?: return false
        return capabilities.hasCapability(NetworkCapabilities.NET_CAPABILITY_INTERNET)
    }

    private fun showOfflineError() {
        webView.loadData(
            """
            <!DOCTYPE html>
            <html>
            <head>
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <style>
                    body {
                        background: #000;
                        color: #ffcc00;
                        font-family: 'Roboto', sans-serif;
                        display: flex;
                        flex-direction: column;
                        align-items: center;
                        justify-content: center;
                        height: 100vh;
                        margin: 0;
                        text-align: center;
                        padding: 20px;
                    }
                    h1 { font-size: 24px; margin-bottom: 10px; }
                    p { color: #888; font-size: 14px; }
                    button {
                        background: #ffcc00;
                        color: #000;
                        border: none;
                        padding: 15px 30px;
                        font-size: 16px;
                        font-weight: bold;
                        border-radius: 4px;
                        margin-top: 20px;
                        cursor: pointer;
                    }
                </style>
            </head>
            <body>
                <h1>⚠️ No Connection</h1>
                <p>Please check your internet connection and try again.</p>
                <button onclick="location.reload()">RETRY</button>
            </body>
            </html>
            """.trimIndent(),
            "text/html",
            "UTF-8"
        )
    }

    private fun acquireWakeLock() {
        // Using FLAG_KEEP_SCREEN_ON in onCreate instead of deprecated wake lock flags
        // This is already set via: window.addFlags(WindowManager.LayoutParams.FLAG_KEEP_SCREEN_ON)
    }

    private fun releaseWakeLock() {
        // No-op: Using FLAG_KEEP_SCREEN_ON instead
    }

    override fun onKeyDown(keyCode: Int, event: KeyEvent?): Boolean {
        // Handle back button - go back in WebView history if possible
        if (keyCode == KeyEvent.KEYCODE_BACK && webView.canGoBack()) {
            webView.goBack()
            return true
        }
        return super.onKeyDown(keyCode, event)
    }

    override fun onResume() {
        super.onResume()
        webView.onResume()
        setupFullscreen()
        acquireWakeLock()
    }

    override fun onPause() {
        super.onPause()
        webView.onPause()
        releaseWakeLock()
    }

    override fun onDestroy() {
        super.onDestroy()
        releaseWakeLock()
        webView.destroy()
    }
}
