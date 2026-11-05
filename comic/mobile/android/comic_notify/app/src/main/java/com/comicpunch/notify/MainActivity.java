package com.comicpunch.notify;

import android.Manifest;
import android.app.AlertDialog;
import android.content.Context;
import android.content.Intent;
import android.content.pm.PackageManager;
import android.net.ConnectivityManager;
import android.net.NetworkCapabilities;
import android.os.Build;
import android.os.Bundle;
import android.util.Log;
import android.view.View;
import android.view.WindowInsetsController;
import android.webkit.JavascriptInterface;
import android.webkit.MimeTypeMap;
import android.webkit.WebChromeClient;
import android.webkit.WebResourceRequest;
import android.webkit.WebResourceResponse;
import android.webkit.WebSettings;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import android.widget.TextView;

import androidx.activity.OnBackPressedCallback;
import androidx.appcompat.app.AppCompatActivity;
import androidx.core.app.ActivityCompat;
import androidx.core.content.ContextCompat;
import androidx.core.graphics.Insets;
import androidx.core.view.ViewCompat;
import androidx.core.view.WindowInsetsCompat;
import androidx.swiperefreshlayout.widget.SwipeRefreshLayout;

import com.google.firebase.messaging.FirebaseMessaging;

import org.json.JSONObject;

import java.io.BufferedReader;
import java.io.ByteArrayInputStream;
import java.io.File;
import java.io.FileInputStream;
import java.io.InputStreamReader;
import java.io.OutputStream;
import java.net.HttpURLConnection;
import java.net.URL;
import java.nio.charset.StandardCharsets;

public class MainActivity extends AppCompatActivity {

    private static final String TAG = "ComicNotify";
    private static final String SERVER_URL = "https://stvfu.com/comic/mobile/server/api";
    private static final String COMIC_URL = "https://stvfu.com/comic/?type=original";

    private WebView wvNews, wvComic;
    private SwipeRefreshLayout swipeNews, swipeComic;
    private View tabNews, tabComic, bottomNav;
    private TextView tabNewsLabel, tabComicLabel;
    private OfflineManager offlineManager;
    private boolean comicLoaded = false;
    private boolean newsLoaded = false;
    private int currentTab = 1; // 0=news, 1=comic
    private int statusBarHeight = 0;
    private int navBarHeight = 0;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_main);

        ViewCompat.setOnApplyWindowInsetsListener(findViewById(android.R.id.content), (v, windowInsets) -> {
            Insets insets = windowInsets.getInsets(WindowInsetsCompat.Type.systemBars());
            statusBarHeight = insets.top;
            navBarHeight = insets.bottom;
            v.setPadding(insets.left, insets.top, insets.right, insets.bottom);
            return WindowInsetsCompat.CONSUMED;
        });

        offlineManager = new OfflineManager(this);

        requestNotificationPermission();
        registerFcmToken();

        bottomNav = findViewById(R.id.bottom_nav);
        swipeNews = findViewById(R.id.swipe_news);
        swipeComic = findViewById(R.id.swipe_comic);
        wvNews = findViewById(R.id.wv_news);
        wvComic = findViewById(R.id.wv_comic);
        tabNews = findViewById(R.id.tab_news);
        tabComic = findViewById(R.id.tab_comic);
        tabNewsLabel = findViewById(R.id.tab_news_label);
        tabComicLabel = findViewById(R.id.tab_comic_label);

        try {
            String vn = getPackageManager().getPackageInfo(getPackageName(), 0).versionName;
            TextView versionLabel = findViewById(R.id.version_label);
            versionLabel.setText("v" + vn);
        } catch (Exception ignored) {}

        setupWebView(wvNews);
        setupWebView(wvComic);

        swipeNews.setOnRefreshListener(() -> {
            wvNews.reload();
            swipeNews.setRefreshing(false);
        });
        swipeComic.setOnRefreshListener(() -> {
            wvComic.reload();
            swipeComic.setRefreshing(false);
        });

        tabNews.setOnClickListener(v -> switchTab(0));
        tabComic.setOnClickListener(v -> switchTab(1));

        getOnBackPressedDispatcher().addCallback(this, new OnBackPressedCallback(true) {
            @Override
            public void handleOnBackPressed() {
                if (currentTab == 1 && wvComic.canGoBack()) {
                    wvComic.goBack();
                } else if (currentTab == 0 && wvNews.canGoBack()) {
                    wvNews.goBack();
                } else if (currentTab == 1) {
                    switchTab(0);
                } else {
                    setEnabled(false);
                    getOnBackPressedDispatcher().onBackPressed();
                }
            }
        });

        switchTab(1);
        checkForUpdate();
    }

    private void setupWebView(WebView wv) {
        WebSettings s = wv.getSettings();
        s.setJavaScriptEnabled(true);
        s.setDomStorageEnabled(true);
        s.setUseWideViewPort(true);
        s.setLoadWithOverviewMode(true);
        s.setBuiltInZoomControls(true);
        s.setDisplayZoomControls(false);
        s.setCacheMode(WebSettings.LOAD_DEFAULT);
        s.setMixedContentMode(WebSettings.MIXED_CONTENT_NEVER_ALLOW);
        s.setAllowFileAccess(true);

        wv.addJavascriptInterface(new OfflineBridge(), "ComicOffline");

        wv.setWebViewClient(new WebViewClient() {
            @Override
            public void onPageFinished(WebView view, String url) {
                super.onPageFinished(view, url);
                if (view == wvComic) {
                    toggleBottomNav(url);
                }
            }

            @Override
            public WebResourceResponse shouldInterceptRequest(WebView view, WebResourceRequest request) {
                String url = request.getUrl().toString();

                if (url.contains("reader.php") && !isNetworkAvailable()) {
                    String mid = parseUrlParam(url, "id");
                    String cid = parseUrlParam(url, "ch");
                    if (mid != null && cid != null && offlineManager.isDownloaded(mid, cid)) {
                        String html = offlineManager.generateReaderHtml(mid, cid);
                        if (html != null) {
                            return new WebResourceResponse("text/html", "UTF-8",
                                    new ByteArrayInputStream(html.getBytes(StandardCharsets.UTF_8)));
                        }
                    }
                }

                if (url.contains("/comic/works/") && isImageUrl(url)) {
                    File local = offlineManager.findLocalFile(url);
                    if (local != null) {
                        try {
                            String mime = getMimeType(local.getName());
                            return new WebResourceResponse(mime, null, new FileInputStream(local));
                        } catch (Exception ignored) {}
                    }
                }
                return super.shouldInterceptRequest(view, request);
            }

            @Override
            public void onReceivedError(WebView view, WebResourceRequest request, android.webkit.WebResourceError error) {
                if (request.isForMainFrame() && !isNetworkAvailable()) {
                    String html = offlineManager.generateOfflineIndexHtml();
                    if (html != null) {
                        view.loadDataWithBaseURL("https://stvfu.com/comic/",
                                html, "text/html", "UTF-8", null);
                        return;
                    }
                }
                super.onReceivedError(view, request, error);
            }
        });

        wv.setWebChromeClient(new WebChromeClient());
    }

    private boolean isNetworkAvailable() {
        ConnectivityManager cm = (ConnectivityManager) getSystemService(Context.CONNECTIVITY_SERVICE);
        if (cm == null) return false;
        android.net.Network net = cm.getActiveNetwork();
        if (net == null) return false;
        NetworkCapabilities caps = cm.getNetworkCapabilities(net);
        return caps != null && caps.hasCapability(NetworkCapabilities.NET_CAPABILITY_INTERNET);
    }

    private String parseUrlParam(String url, String key) {
        int qIdx = url.indexOf('?');
        if (qIdx < 0) return null;
        String query = url.substring(qIdx + 1);
        for (String pair : query.split("&")) {
            String[] kv = pair.split("=", 2);
            if (kv.length == 2 && kv[0].equals(key)) {
                try {
                    return java.net.URLDecoder.decode(kv[1], "UTF-8");
                } catch (Exception e) {
                    return kv[1];
                }
            }
        }
        return null;
    }

    private boolean isImageUrl(String url) {
        String lower = url.toLowerCase();
        return lower.endsWith(".jpg") || lower.endsWith(".jpeg")
                || lower.endsWith(".png") || lower.endsWith(".gif")
                || lower.endsWith(".webp");
    }

    private String getMimeType(String filename) {
        String ext = MimeTypeMap.getFileExtensionFromUrl(filename);
        if (ext != null) {
            String mime = MimeTypeMap.getSingleton().getMimeTypeFromExtension(ext);
            if (mime != null) return mime;
        }
        return "image/jpeg";
    }

    private class OfflineBridge {
        @JavascriptInterface
        public boolean isDownloaded(String mangaId, String chapterId) {
            return offlineManager.isDownloaded(mangaId, chapterId);
        }

        @JavascriptInterface
        public void download(String mangaId, String chapterId) {
            offlineManager.download(mangaId, chapterId);
        }

        @JavascriptInterface
        public int getProgress(String mangaId, String chapterId) {
            return offlineManager.getProgress(mangaId, chapterId);
        }

        @JavascriptInterface
        public void deleteChapter(String mangaId, String chapterId) {
            offlineManager.delete(mangaId, chapterId);
        }

        @JavascriptInterface
        public String getDownloadedList() {
            return offlineManager.getDownloadedList();
        }
    }

    private void toggleBottomNav(String url) {
        boolean isReader = url != null && url.contains("reader.php");
        bottomNav.setVisibility(isReader ? View.GONE : View.VISIBLE);

        View rootView = findViewById(android.R.id.content);
        WindowInsetsController controller = getWindow().getInsetsController();
        if (controller != null) {
            if (isReader) {
                rootView.setPadding(0, 0, 0, 0);
                controller.hide(android.view.WindowInsets.Type.statusBars()
                        | android.view.WindowInsets.Type.navigationBars());
                controller.setSystemBarsBehavior(
                        WindowInsetsController.BEHAVIOR_SHOW_TRANSIENT_BARS_BY_SWIPE);
            } else {
                rootView.setPadding(0, statusBarHeight, 0, navBarHeight);
                controller.show(android.view.WindowInsets.Type.statusBars()
                        | android.view.WindowInsets.Type.navigationBars());
            }
        }
    }

    private void switchTab(int tab) {
        currentTab = tab;

        swipeNews.setVisibility(tab == 0 ? View.VISIBLE : View.GONE);
        swipeComic.setVisibility(tab == 1 ? View.VISIBLE : View.GONE);

        tabNewsLabel.setTextColor(tab == 0 ? 0xFFE94560 : 0xFF888888);
        tabComicLabel.setTextColor(tab == 1 ? 0xFFE94560 : 0xFF888888);

        if (tab == 0 && !newsLoaded) {
            wvNews.loadUrl(SERVER_URL + "/updates.php");
            newsLoaded = true;
        }
        if (tab == 1 && !comicLoaded) {
            wvComic.loadUrl(COMIC_URL);
            comicLoaded = true;
        }
    }

    private void requestNotificationPermission() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
            if (ContextCompat.checkSelfPermission(this, Manifest.permission.POST_NOTIFICATIONS)
                    != PackageManager.PERMISSION_GRANTED) {
                ActivityCompat.requestPermissions(this,
                        new String[]{Manifest.permission.POST_NOTIFICATIONS}, 1);
            }
        }
    }

    private void registerFcmToken() {
        FirebaseMessaging.getInstance().getToken().addOnCompleteListener(task -> {
            if (!task.isSuccessful()) {
                Log.w(TAG, "FCM token failed", task.getException());
                return;
            }
            String token = task.getResult();
            Log.d(TAG, "FCM Token: " + token);
            sendTokenToServer(token);
        });
    }

    private void sendTokenToServer(String token) {
        new Thread(() -> {
            try {
                URL url = new URL(SERVER_URL + "/register_token.php");
                HttpURLConnection conn = (HttpURLConnection) url.openConnection();
                conn.setRequestMethod("POST");
                conn.setRequestProperty("Content-Type", "application/x-www-form-urlencoded");
                conn.setDoOutput(true);

                String body = "token=" + java.net.URLEncoder.encode(token, "UTF-8");
                try (OutputStream os = conn.getOutputStream()) {
                    os.write(body.getBytes(StandardCharsets.UTF_8));
                }

                int code = conn.getResponseCode();
                Log.d(TAG, "Token register response: " + code);
                conn.disconnect();
            } catch (Exception e) {
                Log.e(TAG, "Token register failed", e);
            }
        }).start();
    }

    private int[] parseVersion(String v) {
        String[] parts = v.split("\\.");
        int[] result = new int[3];
        for (int i = 0; i < Math.min(parts.length, 3); i++) {
            try { result[i] = Integer.parseInt(parts[i]); } catch (Exception ignored) {}
        }
        return result;
    }

    private void checkForUpdate() {
        new Thread(() -> {
            try {
                URL url = new URL("https://stvfu.com/comic/api.php?action=app_version");
                HttpURLConnection conn = (HttpURLConnection) url.openConnection();
                conn.setConnectTimeout(5000);
                conn.setReadTimeout(5000);
                BufferedReader reader = new BufferedReader(
                        new InputStreamReader(conn.getInputStream(), StandardCharsets.UTF_8));
                StringBuilder sb = new StringBuilder();
                String line;
                while ((line = reader.readLine()) != null) sb.append(line);
                reader.close();
                conn.disconnect();

                JSONObject data = new JSONObject(sb.toString());
                String remoteVersion = data.optString("version_name", "0.0.0");
                String localVersion = getPackageManager()
                        .getPackageInfo(getPackageName(), 0).versionName;

                int[] rv = parseVersion(remoteVersion);
                int[] lv = parseVersion(localVersion);

                boolean isNewer = rv[0] > lv[0]
                        || (rv[0] == lv[0] && rv[1] > lv[1])
                        || (rv[0] == lv[0] && rv[1] == lv[1] && rv[2] > lv[2]);

                if (isNewer) {
                    boolean majorChange = rv[0] > lv[0];
                    boolean serverRequired = data.optBoolean("required", false);
                    boolean forceUpdate = majorChange || serverRequired;

                    String changelog = data.optString("changelog", "");
                    String downloadUrl = data.optString("download_url", "");

                    runOnUiThread(() -> showUpdateDialog(
                            remoteVersion, changelog, downloadUrl, forceUpdate, majorChange));
                }
            } catch (Exception e) {
                Log.w(TAG, "Update check failed", e);
            }
        }).start();
    }

    private void showUpdateDialog(String version, String changelog, String downloadUrl,
                                  boolean forceUpdate, boolean majorChange) {
        String message = "最新版本：v" + version;
        if (majorChange) {
            message += "\n\n⚠ 重大更新，必須更新才能繼續使用";
        }
        if (!changelog.isEmpty()) message += "\n\n" + changelog;

        AlertDialog.Builder builder = new AlertDialog.Builder(this)
                .setTitle(forceUpdate ? "必須更新" : "有新版本可更新")
                .setMessage(message)
                .setPositiveButton("立即更新", (dialog, which) -> {
                    Intent intent = new Intent(Intent.ACTION_VIEW,
                            android.net.Uri.parse(downloadUrl));
                    startActivity(intent);
                    if (forceUpdate) finish();
                });

        if (!forceUpdate) {
            builder.setNegativeButton("稍後再說", null);
        } else {
            builder.setCancelable(false);
            builder.setNegativeButton("關閉 App", (dialog, which) -> finish());
        }

        builder.show();
    }

}
