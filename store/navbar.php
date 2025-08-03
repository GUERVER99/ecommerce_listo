<?php
// Obtener productos con descuento activo
$now = date('Y-m-d H:i:s');
$stmt = $conn->prepare("SELECT COUNT(*) as count, MIN(descuento_fin) as min_fin 
                       FROM productos 
                       WHERE precio_descuento IS NOT NULL 
                       AND descuento_inicio <= ? 
                       AND descuento_fin >= ?");
$stmt->execute([$now, $now]);
$descuento_info = $stmt->fetch(PDO::FETCH_ASSOC);

$hay_descuentos = $descuento_info['count'] > 0;
$fin_descuento = $descuento_info['min_fin'];

// Obtener cantidad de favoritos si el usuario está logueado
$cantidad_favoritos = 0;
if(isset($_SESSION['usuario_id'])) {
    $stmt_fav = $conn->prepare("SELECT COUNT(*) as count FROM favoritos WHERE usuario_id = ?");
    $stmt_fav->execute([$_SESSION['usuario_id']]);
    $favoritos = $stmt_fav->fetch(PDO::FETCH_ASSOC);
    $cantidad_favoritos = $favoritos['count'];
}
?>

<nav class="navbar navbar-expand-lg navbar-light bg-light sticky-top" style="z-index: 1100;">
    <div class="container">
        <a class="navbar-brand" href="index.php">Khalo's Style</a>
        
        <!-- Notificación de descuentos -->
        <?php if($hay_descuentos): ?>
        <div class="mx-3 discount-alert">
            <a href="ofertas.php" class="text-danger text-decoration-none">
                <i class="fas fa-fire me-1"></i>
                <span class="d-none d-md-inline">OFERTAS</span> 
                <span id="countdown" class="fw-bold">
                    <span id="days">00</span>d 
                    <span id="hours">00</span>h 
                    <span id="minutes">00</span>m 
                    <span id="seconds">00</span>s
                </span>
            </a>
        </div>
        <?php endif; ?>
        
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link" href="index.php">
                        <i class="fas fa-home d-lg-none me-2"></i>
                        <span class="d-none d-lg-inline">Inicio</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="productos.php">
                        <i class="fas fa-tshirt d-lg-none me-2"></i>
                        <span class="d-none d-lg-inline">Productos</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="contacto.php">
                        <i class="fas fa-envelope d-lg-none me-2"></i>
                        <span class="d-none d-lg-inline">Contacto</span>
                    </a>
                </li>
                
                <!-- Menú de usuario mejorado -->
                <?php if(isset($_SESSION['usuario_id'])): ?>
                    <li class="nav-item dropdown position-static">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-user-circle me-1"></i>
                            <span class="d-none d-lg-inline">Mi Cuenta</span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow-lg" aria-labelledby="navbarDropdown" style="z-index: 1101;">
                            <li><a class="dropdown-item" href="perfil.php"><i class="fas fa-user me-2"></i> Perfil</a></li>
                            <li><a class="dropdown-item" href="pedidos.php"><i class="fas fa-box me-2"></i> Mis Pedidos</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i> Cerrar Sesión</a></li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="login.php">
                            <i class="fas fa-sign-in-alt d-lg-none me-2"></i>
                            <span class="d-none d-lg-inline">Iniciar Sesión</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="registro.php">
                            <i class="fas fa-user-plus d-lg-none me-2"></i>
                            <span class="d-none d-lg-inline">Registrarse</span>
                        </a>
                    </li>
                <?php endif; ?>
                
                <!-- Carrito de compras -->
                <li class="nav-item">
                    <a class="nav-link position-relative" href="carrito.php">
                        <i class="fas fa-shopping-cart"></i>
                        <span class="d-none d-lg-inline ms-1">Carrito</span>
                        <?php if(isset($_SESSION['carrito']) && count($_SESSION['carrito']) > 0): ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                <?php echo count($_SESSION['carrito']); ?>
                                <span class="visually-hidden">items en el carrito</span>
                            </span>
                        <?php endif; ?>
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<?php if($hay_descuentos): ?>
<script>
// Contador regresivo para ofertas
function updateCountdown() {
    const endDate = new Date("<?php echo $fin_descuento; ?>").getTime();
    const now = new Date().getTime();
    const distance = endDate - now;
    
    if (distance < 0) {
        document.getElementById("countdown").innerHTML = "¡Ofertas finalizadas!";
        return;
    }
    
    const days = Math.floor(distance / (1000 * 60 * 60 * 24));
    const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
    const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
    const seconds = Math.floor((distance % (1000 * 60)) / 1000);
    
    document.getElementById("days").innerHTML = days.toString().padStart(2, '0');
    document.getElementById("hours").innerHTML = hours.toString().padStart(2, '0');
    document.getElementById("minutes").innerHTML = minutes.toString().padStart(2, '0');
    document.getElementById("seconds").innerHTML = seconds.toString().padStart(2, '0');
}

// Actualizar cada segundo
updateCountdown();
setInterval(updateCountdown, 1000);
</script>
<?php endif; ?>

<style>
/* Estilos para el navbar */
.navbar {
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.discount-alert {
    background-color: #fff3cd;
    padding: 5px 15px;
    border-radius: 20px;
    animation: pulse 2s infinite;
}

.discount-alert a {
    color: #dc3545 !important;
    font-weight: 600;
}

/* Menú desplegable mejorado */
.dropdown-menu {
    border: none;
    box-shadow: 0 5px 15px rgba(0,0,0,0.15);
    min-width: 200px;
    margin-top: 0;
}

.dropdown-item {
    padding: 0.5rem 1.5rem;
    transition: all 0.2s;
}

.dropdown-item:hover {
    background-color: #f8f9fa;
    color: #0d6efd;
}

.dropdown-menu i {
    width: 20px;
    text-align: center;
    margin-right: 10px;
}

/* Badge para favoritos y carrito */
.nav-link .badge {
    font-size: 0.6rem;
    padding: 3px 5px;
    position: relative;
    top: -8px;
    left: -5px;
}

/* Animación para ofertas */
@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); }
}

/* Responsive: mostrar solo iconos en móvil */
@media (max-width: 991.98px) {
    .navbar-nav .nav-link span.d-none {
        display: inline-block !important;
        margin-left: 8px;
    }
    
    .dropdown-menu {
        position: absolute;
        right: 0;
        left: auto;
    }
}
</style>