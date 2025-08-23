<?php
$id = trim($_SERVER['PATH_INFO'] ?? '', '/');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <link rel="icon" type="image/svg+xml" href="/vite.svg" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Shared Draft</title>
</head>
<body>
    <div id="app"></div>
    <script>
        window.DRAFT_ID = "<?php echo htmlspecialchars($id, ENT_QUOTES); ?>";
    </script>
    <script type="module" src="/src/main.ts"></script>
</body>
</html>
