plugins {
    id("com.android.application")
    id("com.google.gms.google-services")
}

android {
    namespace = "com.comicpunch.notify"
    compileSdk = 35

    defaultConfig {
        applicationId = "com.comicpunch.notify"
        minSdk = 26
        targetSdk = 35
        versionCode = 5
        versionName = "0.9.0"
    }

    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
    }
}

dependencies {
    implementation("androidx.appcompat:appcompat:1.6.1")
    implementation("com.google.android.material:material:1.11.0")
    implementation("androidx.swiperefreshlayout:swiperefreshlayout:1.1.0")
    implementation("androidx.cardview:cardview:1.0.0")

    implementation(platform("com.google.firebase:firebase-bom:32.7.0"))
    implementation("com.google.firebase:firebase-messaging")
}
