package com.comicpunch.notify;

import android.content.Context;
import android.util.Log;

import org.json.JSONArray;
import org.json.JSONObject;

import java.io.BufferedReader;
import java.io.File;
import java.io.FileInputStream;
import java.io.FileOutputStream;
import java.io.FileWriter;
import java.io.InputStream;
import java.io.InputStreamReader;
import java.net.HttpURLConnection;
import java.net.URL;
import java.util.concurrent.ConcurrentHashMap;

public class OfflineManager {

    private static final String TAG = "OfflineManager";
    private static final String BASE_API = "https://stvfu.com/comic/api.php";

    private final File baseDir;
    private final ConcurrentHashMap<String, Integer> progress = new ConcurrentHashMap<>();

    public OfflineManager(Context context) {
        this.baseDir = new File(context.getFilesDir(), "offline");
        if (!baseDir.exists()) baseDir.mkdirs();
    }

    private File chapterDir(String mangaId, String chapterId) {
        return new File(baseDir, mangaId + "/" + chapterId);
    }

    public boolean isDownloaded(String mangaId, String chapterId) {
        return new File(chapterDir(mangaId, chapterId), "meta.json").exists();
    }

    public int getProgress(String mangaId, String chapterId) {
        String key = mangaId + "/" + chapterId;
        Integer p = progress.get(key);
        if (p != null) return p;
        return isDownloaded(mangaId, chapterId) ? 100 : -1;
    }

    public void download(String mangaId, String chapterId) {
        String key = mangaId + "/" + chapterId;
        if (progress.containsKey(key) && progress.get(key) >= 0 && progress.get(key) < 100) {
            return;
        }

        new Thread(() -> {
            try {
                String apiUrl = BASE_API + "?action=chapter_images"
                        + "&id=" + java.net.URLEncoder.encode(mangaId, "UTF-8")
                        + "&ch=" + java.net.URLEncoder.encode(chapterId, "UTF-8");

                URL url = new URL(apiUrl);
                HttpURLConnection conn = (HttpURLConnection) url.openConnection();
                String json = readStream(conn.getInputStream());
                conn.disconnect();

                JSONObject data = new JSONObject(json);
                if (data.has("error")) {
                    Log.e(TAG, "API error: " + data.getString("error"));
                    progress.put(key, -1);
                    return;
                }

                JSONArray pages = data.getJSONArray("pages");
                int total = pages.length();
                if (total == 0) {
                    progress.put(key, -1);
                    return;
                }

                File dir = chapterDir(mangaId, chapterId);
                dir.mkdirs();

                progress.put(key, 0);

                String baseUrl = "https://stvfu.com/comic/";
                JSONArray pageMap = new JSONArray();

                for (int i = 0; i < total; i++) {
                    String relPath = pages.getString(i);
                    String fullUrl = baseUrl + relPath;
                    String localName = String.format("%03d", i) + getExtension(relPath);

                    downloadFile(fullUrl, new File(dir, localName));

                    JSONObject entry = new JSONObject();
                    entry.put("url", relPath);
                    entry.put("file", localName);
                    pageMap.put(entry);

                    progress.put(key, (int) ((i + 1.0) / total * 100));
                }

                JSONObject meta = new JSONObject();
                meta.put("manga_id", mangaId);
                meta.put("manga_title", data.getString("manga_title"));
                meta.put("chapter_id", chapterId);
                meta.put("chapter_title", data.getString("chapter_title"));
                meta.put("total", total);
                meta.put("pages", pageMap);
                meta.put("downloaded_at", System.currentTimeMillis());

                writeFile(new File(dir, "meta.json"), meta.toString());
                progress.put(key, 100);
                Log.d(TAG, "Downloaded: " + key + " (" + total + " pages)");

            } catch (Exception e) {
                Log.e(TAG, "Download failed: " + key, e);
                progress.put(key, -1);
            }
        }).start();
    }

    public void delete(String mangaId, String chapterId) {
        deleteRecursive(chapterDir(mangaId, chapterId));
        progress.remove(mangaId + "/" + chapterId);

        File mangaDir = new File(baseDir, mangaId);
        String[] remaining = mangaDir.list();
        if (remaining != null && remaining.length == 0) mangaDir.delete();
    }

    public String getDownloadedList() {
        JSONArray list = new JSONArray();
        try {
            File[] mangaDirs = baseDir.listFiles();
            if (mangaDirs == null) return list.toString();
            for (File mangaDir : mangaDirs) {
                if (!mangaDir.isDirectory()) continue;
                File[] chDirs = mangaDir.listFiles();
                if (chDirs == null) continue;
                for (File chDir : chDirs) {
                    File meta = new File(chDir, "meta.json");
                    if (meta.exists()) {
                        JSONObject obj = new JSONObject(readFile(meta));
                        obj.put("size_mb", dirSizeMB(chDir));
                        list.put(obj);
                    }
                }
            }
        } catch (Exception e) {
            Log.e(TAG, "List failed", e);
        }
        return list.toString();
    }

    public String generateReaderHtml(String mangaId, String chapterId) {
        File dir = chapterDir(mangaId, chapterId);
        File meta = new File(dir, "meta.json");
        if (!meta.exists()) return null;

        try {
            JSONObject obj = new JSONObject(readFile(meta));
            JSONArray pages = obj.getJSONArray("pages");
            String title = esc(obj.optString("chapter_title", ""));
            String mangaTitle = esc(obj.optString("manga_title", ""));

            StringBuilder h = new StringBuilder();
            h.append("<!DOCTYPE html><html lang='zh-Hant'><head>");
            h.append("<meta charset='UTF-8'>");
            h.append("<meta name='viewport' content='width=device-width,initial-scale=1'>");
            h.append("<title>").append(mangaTitle).append(" - ").append(title).append("</title>");
            h.append("<style>");
            h.append("*{margin:0;padding:0;box-sizing:border-box}");
            h.append("body{background:#343434;color:#eee;font-family:-apple-system,'Noto Sans TC',sans-serif}");
            h.append(".hdr{position:sticky;top:0;z-index:10;background:rgba(52,52,52,.95);padding:12px 16px;");
            h.append("display:flex;align-items:center;gap:12px;backdrop-filter:blur(8px);border-bottom:1px solid #444}");
            h.append(".hdr .back{color:#cc6;text-decoration:none;font-size:1.1em;flex-shrink:0}");
            h.append(".hdr .tt{flex:1;font-size:.92em;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}");
            h.append(".hdr .badge{background:#cc6;color:#222;font-size:.7em;padding:2px 8px;border-radius:10px;font-weight:700}");
            h.append(".pages{max-width:900px;margin:0 auto}");
            h.append(".pages img{display:block;width:100%;height:auto}");
            h.append(".end{text-align:center;padding:40px 20px;color:#888;font-size:.95em}");
            h.append("</style></head><body>");
            h.append("<div class='hdr'>");
            h.append("<a class='back' onclick='history.back()'>←</a>");
            h.append("<span class='tt'>").append(mangaTitle).append(" — ").append(title).append("</span>");
            h.append("<span class='badge'>離線</span>");
            h.append("</div>");
            h.append("<div class='pages'>");

            String baseUrl = "https://stvfu.com/comic/";
            for (int i = 0; i < pages.length(); i++) {
                JSONObject page = pages.getJSONObject(i);
                h.append("<img src='").append(baseUrl).append(page.getString("url")).append("' loading='lazy'>");
            }

            h.append("</div>");
            h.append("<div class='end'>— ").append(title).append(" 完 —</div>");
            h.append("</body></html>");
            return h.toString();
        } catch (Exception e) {
            Log.e(TAG, "Generate reader HTML failed", e);
            return null;
        }
    }

    public String generateOfflineIndexHtml() {
        JSONArray list;
        try {
            list = new JSONArray(getDownloadedList());
        } catch (Exception e) {
            return null;
        }
        if (list.length() == 0) return null;

        StringBuilder h = new StringBuilder();
        h.append("<!DOCTYPE html><html lang='zh-Hant'><head>");
        h.append("<meta charset='UTF-8'>");
        h.append("<meta name='viewport' content='width=device-width,initial-scale=1'>");
        h.append("<title>離線閱讀</title>");
        h.append("<style>");
        h.append("*{margin:0;padding:0;box-sizing:border-box}");
        h.append("body{background:#343434;color:#eee;font-family:-apple-system,'Noto Sans TC',sans-serif;padding:20px;max-width:600px;margin:0 auto}");
        h.append("h1{font-size:1.3em;margin-bottom:6px}");
        h.append(".sub{color:#888;font-size:.85em;margin-bottom:24px}");
        h.append(".ch{display:block;padding:16px;margin-bottom:10px;background:#3e3e3e;border-radius:10px;color:#eee;text-decoration:none;transition:background .15s}");
        h.append(".ch:active{background:#4a4a4a}");
        h.append(".ch .title{font-weight:600;font-size:.95em}");
        h.append(".ch .meta{color:#888;font-size:.8em;margin-top:4px}");
        h.append(".badge{display:inline-block;background:#cc6;color:#222;font-size:.7em;padding:2px 8px;border-radius:10px;font-weight:700;margin-left:8px}");
        h.append(".retry{display:block;text-align:center;margin-top:30px;padding:14px;background:#cc6;color:#222;border-radius:8px;font-weight:700;text-decoration:none}");
        h.append("</style></head><body>");
        h.append("<h1>離線閱讀 <span class='badge'>").append(list.length()).append(" 章</span></h1>");
        h.append("<p class='sub'>已下載的章節可在無網路時閱讀</p>");

        try {
            for (int i = 0; i < list.length(); i++) {
                JSONObject ch = list.getJSONObject(i);
                String mid = ch.getString("manga_id");
                String cid = ch.getString("chapter_id");
                String url = "https://stvfu.com/comic/reader.php?id=" + mid + "&ch=" + cid;
                h.append("<a class='ch' href='").append(url).append("'>");
                h.append("<div class='title'>").append(esc(ch.optString("manga_title", ""))).append("</div>");
                h.append("<div class='meta'>").append(esc(ch.optString("chapter_title", "")));
                h.append(" · ").append(ch.optInt("total", 0)).append(" 頁");
                double mb = ch.optDouble("size_mb", 0);
                if (mb > 0) h.append(" · ").append(mb).append(" MB");
                h.append("</div></a>");
            }
        } catch (Exception ignored) {}

        h.append("<a class='retry' onclick='location.reload()'>重新連線</a>");
        h.append("</body></html>");
        return h.toString();
    }

    private String esc(String s) {
        return s.replace("&", "&amp;").replace("<", "&lt;").replace(">", "&gt;")
                .replace("'", "&#39;").replace("\"", "&quot;");
    }

    public File findLocalFile(String requestUrl) {
        String relPath = requestUrl.replace("https://stvfu.com/comic/", "");

        File[] mangaDirs = baseDir.listFiles();
        if (mangaDirs == null) return null;

        for (File mangaDir : mangaDirs) {
            if (!mangaDir.isDirectory()) continue;
            File[] chDirs = mangaDir.listFiles();
            if (chDirs == null) continue;
            for (File chDir : chDirs) {
                File meta = new File(chDir, "meta.json");
                if (!meta.exists()) continue;
                try {
                    JSONObject obj = new JSONObject(readFile(meta));
                    JSONArray pages = obj.getJSONArray("pages");
                    for (int i = 0; i < pages.length(); i++) {
                        JSONObject page = pages.getJSONObject(i);
                        if (relPath.equals(page.getString("url"))) {
                            File local = new File(chDir, page.getString("file"));
                            if (local.exists()) return local;
                        }
                    }
                } catch (Exception ignored) {}
            }
        }
        return null;
    }

    private String getExtension(String path) {
        int dot = path.lastIndexOf('.');
        return dot >= 0 ? path.substring(dot) : ".jpg";
    }

    private void downloadFile(String urlStr, File dest) throws Exception {
        URL url = new URL(urlStr);
        HttpURLConnection conn = (HttpURLConnection) url.openConnection();
        try (InputStream in = conn.getInputStream();
             FileOutputStream out = new FileOutputStream(dest)) {
            byte[] buf = new byte[8192];
            int n;
            while ((n = in.read(buf)) != -1) out.write(buf, 0, n);
        }
        conn.disconnect();
    }

    private String readStream(InputStream in) throws Exception {
        BufferedReader reader = new BufferedReader(new InputStreamReader(in, "UTF-8"));
        StringBuilder sb = new StringBuilder();
        String line;
        while ((line = reader.readLine()) != null) sb.append(line);
        reader.close();
        return sb.toString();
    }

    private String readFile(File f) throws Exception {
        return readStream(new FileInputStream(f));
    }

    private void writeFile(File f, String content) throws Exception {
        try (FileWriter w = new FileWriter(f)) { w.write(content); }
    }

    private void deleteRecursive(File f) {
        if (f.isDirectory()) {
            File[] children = f.listFiles();
            if (children != null) {
                for (File c : children) deleteRecursive(c);
            }
        }
        f.delete();
    }

    private double dirSizeMB(File dir) {
        long size = 0;
        File[] files = dir.listFiles();
        if (files != null) {
            for (File f : files) size += f.length();
        }
        return Math.round(size / 1024.0 / 1024.0 * 10) / 10.0;
    }
}
