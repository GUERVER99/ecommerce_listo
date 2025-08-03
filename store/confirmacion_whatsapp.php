<?php
require_once 'config.php';

if(!isset($_SESSION['url_whatsapp']) || !isset($_GET['pedido_id'])) {
    header("Location: pedidos.php");
    exit;
}

$pedido_id = (int)$_GET['pedido_id'];
$url_whatsapp = $_SESSION['url_whatsapp'];
unset($_SESSION['url_whatsapp']);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmación - Kalo's Style</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .confirmation-icon {
            font-size: 5rem;
            color: #25D366;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <?php include 'navbar.php'; ?>
    
    <!-- Contenido principal -->
    <main class="container my-5">
        <div class="row justify-content-center">
            <div class="col-lg-6 text-center">
                <div class="confirmation-icon">
                    <i class="fab fa-whatsapp"></i>
                </div>
                <h1 class="mb-3">¡Instrucciones enviadas!</h1>
                <p class="lead mb-4">Hemos enviado las instrucciones de pago a tu WhatsApp. Por favor completa la transferencia y envía el comprobante.</p>
                
                <div class="d-flex justify-content-center gap-3">
                    <a href="<?php echo $url_whatsapp; ?>" class="btn btn-success btn-lg whatsapp-bg">
                        <i class="fab fa-whatsapp me-2"></i> Abrir WhatsApp
                    </a>
                    <a href="detalle_pedido.php?id=<?php echo $pedido_id; ?>" class="btn btn-outline-secondary btn-lg">
                        <i class="fas fa-clipboard-list me-2"></i> Ver pedido
                    </a>
                </div>
                
                <div class="alert alert-info mt-4">
                    <h5><i class="fas fa-info-circle me-2"></i>¿Problemas con el enlace?</h5>
                    <p class="mb-0">Si no se abre WhatsApp automáticamente, envíanos un mensaje al +56912345678 con tu número de pedido #<?php echo $pedido_id; ?></p>
                </div>
            </div>
        </div>
    </main>
    
    <!-- Footer -->
    <?php include 'footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Redirigir automáticamente después de 3 segundos
        setTimeout(function() {
            window.location.href = "<?php echo $url_whatsapp; ?>";
        }, 3000);
    </script>
</body>
</html>