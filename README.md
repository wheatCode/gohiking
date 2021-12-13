# hiking-backend
部署版本連結：[https://staging-server.gohiking.app/](https://staging-server.gohiking.app/)

## 專案安裝步驟

```
npm i // 安裝node.js套件，內建畫面模板使用的
composer i // 安裝php套件，後端用到的
cp .env.example .env // 並在.env填入環境變數
php artisan key:generate // 產生網站專屬密鑰(寫入.env環境變數)，確保加密資料安全性(跳過這步網站會無法運作)
// 以下為更動資料格式(非重新安裝)後仍需操作的部分
php artisan migrate:fresh --seed // 將資料庫初始化，且有seeder時會載入(重複執行會清空資料)
php artisan passport:install // 建立產生安全Access Token 的加密金鑰才能執行，非重新安裝時需要加上--force覆寫既有金鑰
php artisan serve // 測試能否運作
```

### 若使用SQLITE的額外步驟
```
sudo apt-get install php-sqlite3 // 以Ubuntu為例，其他作業系統則是安裝對應版本的sqlite
touch ./database/database.sqlite
// 將.env的DB_CONNECTION=mysql改成DB_CONNECTION=sqlite，SESSION_DRIVER=database改成SESSION_DRIVER=file
```

## 身分驗證

### 帳密註冊
1. 發送POST /api/register，Body(x-www-form-urlencoded)需攜帶：

```
{
  "email": "(使用者輸入的email)",
  "password": "(使用者輸入的對應密碼)"
}
```
2. 回傳格式如下：
```
{
    "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...(TLDR)",
    "userId": "8",
    "expireTime": 3600000
}
```
3. 前端若有設定自動攜帶上述token於headers，註冊後不必額外登入即可使用
4. 錯誤的回應(代表電子郵件已被註冊)，並回傳404狀態碼：
```
{
    "error": "wrong email or password!"
}
```

### 建立個人資料
0. 前端需設定攜帶上述token於headers
1. 發送POST /api/profile，Body(x-www-form-urlencoded)需攜帶：
```
{
  "name": "姓名",
  "gender": 性別(1為男性，0為女性，null未指定),
  "phone_number": "手機號碼",
  "country_code_id": 該筆電話國碼(國家/地區代碼)資料的id
  "birth": "生日(西元年/月/日)",
  "county_id": 該筆居住地(縣市代碼)資料的id
}
```
2. 回傳格式如下：
```
{
  "status":"your profile is created!"
}
```
3. 錯誤的回應(代表要建立資料的帳號不存在)，並回傳401狀態碼：
```
{
    "error": "this account is missing!"
}
```

### 帳密登入
1. 發送POST /api/login，Body(x-www-form-urlencoded)需攜帶：

```
{
  "email": "(使用者輸入的email)",
  "password": "(使用者輸入的對應密碼)"
}
```
2. 回傳格式如下：
```
{
    "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...(TLDR)",
    "userId": "8",
    "expireTime": 3600000
}
```
3. 前端需設定攜帶上述token於headers，才能成立登入狀態存取需驗證的API
4. 錯誤的回應(代表登入失敗)，並回傳401狀態碼：
```
{
    "error": "wrong email or password!"
}
```

### 第三方登入
0. 目前僅支援Facebook、Google、Apple，需由前端向社群平台驗證後取得帳戶資料
1. 發送POST /api/auth/social/callback，Body(x-www-form-urlencoded)需攜帶：

```
{
  "name": "姓名",
  "email": "電子郵件地址",
  "facebook_id": "(id部分僅會因應社群平台來源，只傳其中一個)",
  "google_id": "(id部分僅會因應社群平台來源，只傳其中一個)",
  "apple_id": "(id部分僅會因應社群平台來源，只傳其中一個)",
  "avatar": "大頭貼，圖片URL",
  "token": "(用於產生密碼雜湊)"
}
```
2. 回傳格式如下：
```
{
    "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...(TLDR)",
    "userId": "8",
    "expireTime": 3600000
}
```

### 登入測試
1. 以發送GET /api/index為例，驗證成功會收到：
```
{
    "status": "logged!"
}
```
2. token錯誤的回應：
```
{
    "status": "incorrect token"
}
```

### 忘記密碼
1. 發送POST /api/password/forget，Body(x-www-form-urlencoded)只需攜帶：

```
{
  "email": "(使用者輸入的email)"
}
```
2. 回傳格式如下：
```
{
    "message": "已寄送驗證碼到指定信件！"
}
```
3. 同時，只要查有對應帳號的信箱，也會用電子郵件發送4位數字的驗證碼(如4, 3, 2, 1)，主旨為：請確認修改密碼

### 確認驗證碼
1. 發送POST /api/password/confirm，Body(x-www-form-urlencoded)需攜帶：

```
// 此時驗證碼充當密碼驗證
{
  "email": "(使用者輸入的email)",
  "verificationCode0": "(第0個驗證碼(以陣列方式計算)",
  "verificationCode1": "(第1個驗證碼(以陣列方式計算)",
  "verificationCode2": "(第2個驗證碼(以陣列方式計算)",
  "verificationCode3": "(第3個驗證碼(以陣列方式計算)",
}
```
2. 若驗證碼與電子郵件的一致，給予登入的權限，回傳格式如下：
```
{
    "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...(TLDR)"
}
```
3. 錯誤的回應(代表驗證碼錯誤)，並回傳401狀態碼：
```
{
    "error": "wrong verification codes!"
}
```

### 重設密碼
0. 前端需設定攜帶token於headers
1. 發送POST /api/password/change，Body(x-www-form-urlencoded)需攜帶：
```
{
  "password": "(欲重設的密碼)"
}
```
2. 回傳格式如下：
```
{
  "status":"your password has been changed!"
}
```
3. 錯誤的回應(代表要建立資料的帳號不存在)，並回傳401狀態碼：
```
{
    "error": "this account is missing!"
}
```

## 其他API(步道、景點......)
WIP

## 附註

### Heroku專用seeder/factory設定
- DatabaseSeeder.php改成：
```
function autoIncrementTweak($id)
{
    $range = 4; // 根據ClearDB設定
    return $id * 10 - 10 + $range;

    // return $id; // 本機設定
}
```

- UserFactory.php改成：
```
function factoryAutoIncrementTweak($id)
{
    $range = 4; // 根據ClearDB設定
    return $id * 10 - 10 + $range;

    // return $id; // 本機設定
}
```

### token期限相關設定(供前端參考)
```
// 設定token的有效期
//  app/Providers/AuthServiceProvider.php 
Passport::tokensExpireIn(now()->addHours(1)); // 設定使用期限，1小時到期
Passport::refreshTokensExpireIn(now()->addDays(1)); // 設定可刷新的期限，1天內可更新持續用
Passport::personalAccessTokensExpireIn(now()->addMonths(1)); // 設定可存取期限，1個月內
```
