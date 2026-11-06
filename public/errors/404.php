<?php
/**
 * Solvex Report — 404 Page Not Found
 */
http_response_code(404);
$pageTitle = '404 Page Not Found';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 — Not Found — Solvex Report</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #F8FAFC; color: #0B2545; }
        .error-card { max-width: 540px; margin: 80px auto; background: #fff; border-radius: 16px; padding: 40px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); text-align: center; }
        .btn-teal { background-color: #0E9F8E; color: #fff; border-radius: 8px; padding: 10px 24px; text-decoration: none; font-weight: 600; display: inline-block; }
        .btn-teal:hover { background-color: #0b8072; color: #fff; }
    </style>
</head>
<body>
    <div class="container">
        <div class="error-card">
            <img src="/assets/img/error-404.webp" alt="404 Not Found" width="220" height="154" class="mb-4">
            <h1 class="h3 fw-bold mb-2">404 &mdash; Page Not Found</h1>
            <p class="text-secondary small mb-4">The ticket, resource, or page you requested does not exist, has been archived, or is restricted under campus privacy policies.</p>
            <a href="/" class="btn-teal"><i class="bi bi-house-door me-1"></i> Return to Campus Homepage</a>
        </div>
    </div>
</body>
</html>
