<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 — <?= View::escape(Config::get('site_name')) ?></title>
    <link rel="stylesheet" href="/assets/css/main-bundle.css">

    <style>
        body { display: flex; align-items: center; justify-content: center; min-height: 100vh; text-align: center; }
        .error-wrap h1 { font-size: 80px; font-weight: 900; color: var(--accent); margin-bottom: 10px; }
        .error-wrap p { font-size: 16px; color: var(--text-dim); margin-bottom: 24px; }
        .error-wrap a { display: inline-block; padding: 12px 28px; background: var(--accent); color: #fff; border-radius: 10px; font-weight: 700; }
    </style>
</head>
<body>
    <div class="error-wrap">
        <h1>404</h1>
        <p>The page you're looking for doesn't exist.</p>
        <a href="/home">Go to Homepage</a>
    </div>
</body>
</html>
