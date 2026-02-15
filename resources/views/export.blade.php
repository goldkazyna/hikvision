<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Экспорт пользователей</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #1a1a2e; color: #fff; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
        .container { text-align: center; background: #16213e; padding: 40px 60px; border-radius: 16px; box-shadow: 0 8px 32px rgba(0,0,0,0.3); }
        h1 { margin-bottom: 12px; font-size: 24px; }
        .count { color: #aaa; margin-bottom: 30px; font-size: 16px; }
        .btn { display: inline-block; padding: 14px 36px; background: #e94560; color: #fff; text-decoration: none; border-radius: 8px; font-size: 18px; font-weight: bold; transition: background 0.2s; }
        .btn:hover { background: #c73a52; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Экспорт пользователей</h1>
        <p class="count">Всего в базе: {{ $usersCount }} пользователей</p>
        <a href="/export/users" class="btn">Скачать Excel (CSV)</a>
    </div>
</body>
</html>
