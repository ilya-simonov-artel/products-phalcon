<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ appName }}</title>
    {% if mainCss %}
        <link rel="stylesheet" href="{{ mainCss }}">
    {% endif %}
    {% if mainJs %}
        <script type="module" src="{{ mainJs }}"></script>
    {% endif %}
</head>
<body>
    <div id="app"></div>
    <script>
        window.APP_NAME = "{{ appName|escape_js }}";
    </script>
</body>
</html>
