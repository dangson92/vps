<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VPS Manager</title>
    <link rel="icon" type="image/svg+xml" href="/favicon.ico" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <script id="tailwind-config">
      tailwind.config = {
        darkMode: 'class',
        theme: {
          extend: {
            colors: {
              primary: '#137fec',
              'background-light': '#f6f7f8',
              'background-dark': '#101922'
            },
            fontFamily: { display: ['Inter', 'sans-serif'] },
            borderRadius: { DEFAULT: '0.25rem', lg: '0.5rem', xl: '0.75rem', full: '9999px' }
          }
        }
      }
    </script>
</head>
<body class="font-display">
    <div id="app"></div>
    <script type="module" src="/dist/assets/main.js"></script>
</body>
</html><?php /**PATH /opt/vps-manager/resources/views/app.blade.php ENDPATH**/ ?>