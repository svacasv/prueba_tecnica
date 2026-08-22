<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Rick and Morty API · Documentación</title>
    <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5/swagger-ui.css">
</head>
<body>
    <div id="swagger-ui"></div>
    <script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js" crossorigin></script>
    <script>
        // Swagger UI lee la especificación OpenAPI que sirve la propia aplicación.
        window.onload = () => SwaggerUIBundle({
            url: '{{ url('/api/docs') }}',
            dom_id: '#swagger-ui',
        });
    </script>
</body>
</html>
