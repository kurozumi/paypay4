# PayPay for EC-CUBE4

EC-CUBE4でPayPay決済ができるプラグインのサンプルです。
非公式プラグインですのでご利用は自己責任でお願い致します。

## インストールと有効化

```
bin/console eccube:composer:require paypayopa/php-sdk

bin/console eccube:plugin:install --code paypay4
bin/console eccube:plugin:enable --code paypay4
```

## シークレットキーと公開キーを設定

PayPay for DevelopersでAPIキーとシークレットキー、マーチャントIDを取得して、
環境変数(.env)に設定してください。

```
## APIキー
PAYPAY_API_KEY=********************
## シークレットキー
PAYPAY_API_SECRET=********************
## マーチャントID
PAYPAY_MERCHANT_ID=********************
## 1は本番環境、0はテスト環境
PAYPAY_PROD=0
```

## 配送方法設定でPayPayを設定

配送方法設定で取り扱う支払い方法にPayPayを追加してください。
