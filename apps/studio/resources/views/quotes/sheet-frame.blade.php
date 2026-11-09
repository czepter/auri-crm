<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Angebot</title>
    <style>
        body { margin: 0; background: #ECECEA; font: 14px/1.4 'Hanken Grotesk', 'Helvetica Neue', Arial, sans-serif; }
        nav { padding: 16px 24px; }
        nav a { color: #191919; text-decoration: none; font-weight: 500; }
        nav a:hover { text-decoration: underline; }
        main { padding: 0 16px 48px; }
        iframe { display: block; width: 100%; max-width: 794px; min-height: 1123px; margin: 0 auto; border: 0; background: #fff; box-shadow: 0 18px 40px -24px rgba(20, 16, 14, .28); }
    </style>
</head>
<body>
    <nav><a href="{{ $back }}">← Zurück zum Angebot</a></nav>
    <main>
        <iframe src="{{ $source }}" onload="this.style.height = this.contentDocument.documentElement.scrollHeight + 'px'"></iframe>
    </main>
</body>
</html>
