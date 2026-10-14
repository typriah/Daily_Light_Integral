#define FASTLED_ESP32_I2S false
#define FASTLED_RMT_MAX_CHANNELS 1

#include <Wire.h>
#include "DFRobot_B_LUX_V30B.h"
#include <FastLED.h>
#include <WiFi.h>
#include <WebServer.h>
#include <ArduinoJson.h>
#include <DHT.h>

const char* WIFI_SSID     = "vergissmeinnicht.";
const char* WIFI_PASSWORD = "12345678";
const char* SERVER_HOST   = "10.189.5.108";
const int   SERVER_PORT   = 12345;

#define LED_PIN       4
#define NUM_LEDS      144
#define LED_TYPE      WS2812B
#define COLOR_ORDER   GRB
#define EN_PIN        13
#define DHTPIN        5
#define DHTTYPE       DHT22

const float LUX_TARGET           = 5000.0;
const float LUX_MINIMUM          = 500.0;
const unsigned long SEND_INTERVAL_MS = 5000;

CRGB          leds[NUM_LEDS];
DFRobot_B_LUX_V30B myLux(EN_PIN);
DHT           dht(DHTPIN, DHTTYPE);
WiFiClient    tcpClient;
WebServer     httpServer(80);

struct RGBWState {
    uint8_t r = 70;
    uint8_t g = 20;
    uint8_t b = 50;
    uint8_t w = 30;
} rgbwState;

bool    ledOn         = false;
uint8_t ledBrightness = 0;

float         cumulativeDLI = 0.0;
unsigned long lastSampleMs  = 0;
unsigned long lastSendMs    = 0;

float luxToPpfd(float lux) {
    return lux * 0.0185f;
}

void applyLEDs() {
    if (!ledOn || ledBrightness == 0) {
        FastLED.setBrightness(0);
        FastLED.clear();
        FastLED.show();
        return;
    }

    uint8_t rVal = map(rgbwState.r, 0, 100, 0, 255);
    uint8_t gVal = map(rgbwState.g, 0, 100, 0, 255);
    uint8_t bVal = map(rgbwState.b, 0, 100, 0, 255);
    uint8_t wVal = map(rgbwState.w, 0, 100, 0, 255);

    uint8_t rFinal = (uint8_t)min(255, (int)rVal + (int)wVal);
    uint8_t gFinal = (uint8_t)min(255, (int)gVal + (int)wVal);
    uint8_t bFinal = (uint8_t)min(255, (int)bVal + (int)wVal);

    if (rFinal == 0 && gFinal == 0 && bFinal == 0) {
        rFinal = gFinal = bFinal = 10;
    }

    FastLED.setBrightness(ledBrightness);
    fill_solid(leds, NUM_LEDS, CRGB(rFinal, gFinal, bFinal));
    FastLED.show();

    Serial.printf("[LED] R=%d G=%d B=%d W->RGB=%d | Brightness=%d\n",
                  rVal, gVal, bVal, wVal, ledBrightness);
}

void handleSetLight() {
    httpServer.sendHeader("Access-Control-Allow-Origin", "*");
    httpServer.sendHeader("Access-Control-Allow-Methods", "POST, OPTIONS");
    httpServer.sendHeader("Access-Control-Allow-Headers", "Content-Type");

    if (httpServer.method() == HTTP_OPTIONS) {
        httpServer.send(204);
        return;
    }

    if (httpServer.method() != HTTP_POST) {
        httpServer.send(405, "application/json", "{\"success\":false,\"message\":\"Gunakan POST\"}");
        return;
    }

    String body = httpServer.arg("plain");

    JsonDocument doc;
    DeserializationError err = deserializeJson(doc, body);

    if (err) {
        httpServer.send(400, "application/json",
                        "{\"success\":false,\"message\":\"JSON tidak valid\"}");
        return;
    }

    if (!doc["r"].is<int>() || !doc["g"].is<int>() ||
        !doc["b"].is<int>() || !doc["w"].is<int>()) {
        httpServer.send(422, "application/json",
                        "{\"success\":false,\"message\":\"Field r/g/b/w wajib ada\"}");
        return;
    }

    rgbwState.r = constrain((int)doc["r"], 0, 100);
    rgbwState.g = constrain((int)doc["g"], 0, 100);
    rgbwState.b = constrain((int)doc["b"], 0, 100);
    rgbwState.w = constrain((int)doc["w"], 0, 100);

    Serial.printf("[RGBW] Update -> R:%d%% G:%d%% B:%d%% W:%d%%\n",
                  rgbwState.r, rgbwState.g, rgbwState.b, rgbwState.w);

    applyLEDs();

    char resp[128];
    snprintf(resp, sizeof(resp),
             "{\"success\":true,\"applied\":{\"r\":%d,\"g\":%d,\"b\":%d,\"w\":%d}}",
             rgbwState.r, rgbwState.g, rgbwState.b, rgbwState.w);
    httpServer.send(200, "application/json", resp);
}

void handleStatus() {
    float lux  = myLux.lightStrengthLux();
    float ppfd = luxToPpfd(lux);
    char resp[256];
    snprintf(resp, sizeof(resp),
             "{\"lux\":%.2f,\"ppfd\":%.2f,\"dli\":%.4f,"
             "\"led_on\":%s,\"brightness\":%d,"
             "\"rgbw\":{\"r\":%d,\"g\":%d,\"b\":%d,\"w\":%d}}",
             lux, ppfd, cumulativeDLI,
             ledOn ? "true" : "false", ledBrightness,
             rgbwState.r, rgbwState.g, rgbwState.b, rgbwState.w);
    httpServer.sendHeader("Access-Control-Allow-Origin", "*");
    httpServer.send(200, "application/json", resp);
}

void handleNotFound() {
    httpServer.send(404, "application/json", "{\"error\":\"endpoint tidak ditemukan\"}");
}

void connectToWiFi() {
    WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
    Serial.print("[WiFi] Menghubungkan");
    while (WiFi.status() != WL_CONNECTED) {
        delay(500);
        Serial.print(".");
    }
    Serial.println();
    Serial.print("[WiFi] Terhubung, IP: ");
    Serial.println(WiFi.localIP());
}

void connectToTCP() {
    while (!tcpClient.connected()) {
        Serial.printf("[TCP] Menghubungkan ke %s:%d ...", SERVER_HOST, SERVER_PORT);
        if (tcpClient.connect(SERVER_HOST, SERVER_PORT)) {
            Serial.println(" OK");
        } else {
            Serial.println(" gagal, coba 5 detik");
            delay(5000);
        }
    }
}

void setup() {
    Serial.begin(9600);
    delay(1000);

    connectToWiFi();
    connectToTCP();

    myLux.begin();
    dht.begin();

    FastLED.addLeds<LED_TYPE, LED_PIN, COLOR_ORDER>(leds, NUM_LEDS)
           .setCorrection(TypicalLEDStrip);
    FastLED.setMaxPowerInVoltsAndMilliamps(5, 2000);
    FastLED.clear();
    FastLED.show();

    httpServer.on("/set_light", handleSetLight);
    httpServer.on("/status",    handleStatus);
    httpServer.onNotFound(handleNotFound);
    httpServer.begin();
    Serial.println("[HTTP] Server aktif di port 80");

    lastSampleMs = millis();
    Serial.println("[ESP32] Siap");
}

void loop() {
    httpServer.handleClient();

    if (WiFi.status() != WL_CONNECTED) connectToWiFi();
    if (!tcpClient.connected())         connectToTCP();

    unsigned long now = millis();

    float lux  = myLux.lightStrengthLux();
    float ppfd = luxToPpfd(lux);

    float elapsedSec = (now - lastSampleMs) / 1000.0f;
    if (elapsedSec > 0 && elapsedSec < 600) {
        cumulativeDLI += ppfd * elapsedSec / 1e6f;
    }
    lastSampleMs = now;

    if (lux >= LUX_TARGET) {
        ledOn         = false;
        ledBrightness = 0;
    } else {
        ledOn         = true;
        ledBrightness = (uint8_t)constrain(
            map((long)lux, (long)LUX_MINIMUM, (long)LUX_TARGET, 255, 30),
            0, 255
        );
    }

    applyLEDs();

    float temperature = dht.readTemperature();
    float humidity    = dht.readHumidity();
    if (isnan(temperature)) temperature = 25.0f;
    if (isnan(humidity))    humidity    = 60.0f;

    if (now - lastSendMs >= SEND_INTERVAL_MS) {
        lastSendMs = now;

        char source[32];
        snprintf(source, sizeof(source), "ESP32_R%dG%dB%dW%d",
                 rgbwState.r, rgbwState.g, rgbwState.b, rgbwState.w);

        char buffer[220];
        snprintf(buffer, sizeof(buffer),
                 "%.2f,%.2f,%.4f,%d,%ld,%.1f,%.1f,%s\n",
                 lux, ppfd, cumulativeDLI,
                 ledBrightness, SEND_INTERVAL_MS / 1000,
                 temperature, humidity,
                 source);

        tcpClient.print(buffer);
        Serial.print("[TCP] Kirim: ");
        Serial.print(buffer);
    }

    delay(10);
}
