package com.comicpunch.notify;

import android.Manifest;
import android.content.pm.PackageManager;
import android.os.Build;
import android.os.Bundle;
import android.util.Log;
import android.view.View;
import android.view.WindowManager;
import android.widget.TextView;

import androidx.appcompat.app.AppCompatActivity;
import androidx.core.app.ActivityCompat;
import androidx.core.content.ContextCompat;
import androidx.swiperefreshlayout.widget.SwipeRefreshLayout;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import android.webkit.WebSettings;
import android.webkit.WebChromeClient;

import com.google.firebase.messaging.FirebaseMessaging;

import java.io.OutputStream;
import java.net.HttpURLConnection;
import java.net.URL;
import java.nio.charset.StandardCharsets;

public class MainActivity extends AppCompatActivity {

    private static final String TAG = "ComicNotify";
    private static final String SERVER_URL = "https://stvfu.com/comic/mobile/server/api";
    private static final String COMIC_URL = "https://stvfu.com/comic/";

    private WebView wvNews, wvComic;
    private SwipeRefreshLayout swipeNews, swipeComic;
    private View tabNews, tabComic, bottomNav;
    private TextView tabNewsLabel, tabComicLabel;
    private boolean comicLoaded = false;
    private int currentTab = 0; // 0=news, 1=comic

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_main);

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

        setupWebView(wvNews);
        setupWebView(wvComic);

        wvNews.loadUrl(SERVER_URL + "/updates.php");

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

        wv.setWebViewClient(new WebViewClient() {
            @Override
            public void onPageFinished(WebView view, String url) {
                super.onPageFinished(view, url);
                if (view == wvComic) {
                    toggleBottomNav(url);
                }
            }
        });

        wv.setWebChromeClient(new WebChromeClient());
    }

    private void toggleBottomNav(String url) {
        if (url != null && url.contains("reader.php")) {
            bottomNav.setVisibility(View.GONE);
            getWindow().addFlags(WindowManager.LayoutParams.FLAG_FULLSCREEN);
        } else {
            bottomNav.setVisibility(View.VISIBLE);
            getWindow().clearFlags(WindowManager.LayoutParams.FLAG_FULLSCREEN);
        }
    }

    private void switchTab(int tab) {
        currentTab = tab;

        swipeNews.setVisibility(tab == 0 ? View.VISIBLE : View.GONE);
        swipeComic.setVisibility(tab == 1 ? View.VISIBLE : View.GONE);

        tabNewsLabel.setTextColor(tab == 0 ? 0xFFE94560 : 0xFF888888);
        tabComicLabel.setTextColor(tab == 1 ? 0xFFE94560 : 0xFF888888);

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

    @Override
    public void onBackPressed() {
        if (currentTab == 1 && wvComic.canGoBack()) {
            wvComic.goBack();
        } else if (currentTab == 0 && wvNews.canGoBack()) {
            wvNews.goBack();
        } else if (currentTab == 1) {
            switchTab(0);
        } else {
            super.onBackPressed();
        }
    }
}
