<nav class="navbar navbar-expand-lg navbar-light bg-light sticky-top shadow-sm">
    <div class="container">
        <a class="navbar-brand fw-bold" href="<?php echo (isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] == 'admin') ? 'admin/dashboard.php' : 'index.php'; ?>">
            <i class="fas fa-tshirt me-2"></i>Kalo's Style
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <?php if(!isset($_SESSION['usuario_rol']) || $_SESSION['usuario_rol'] != 'admin'): ?>
                <li class="nav-item">
                    <a class="nav-link" href="index.php"><i class="fas fa-home me-1"></i> Inicio</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="productos.php"><i class="fas fa-tshirt me-1"></i> Productos</a>
                </li>
                <?php if(isset($_SESSION['usuario_id']) && $_SESSION['usuario_rol'] == 'cliente'): ?>
                <li class="nav-item">
                    <a class="nav-link" href="pedidos.php"><i class="fas fa-clipboard-list me-1"></i> Mis Pedidos</a>
                </li>
                <?php endif; ?>
                <?php endif; ?>
                
                <?php if(isset($_SESSION['usuario_id']) && $_SESSION['usuario_rol'] == 'admin'): ?>
                <li class="nav-item">
                    <a class="nav-link" href="admin/dashboard.php"><i class="fas fa-tachometer-alt me-1"></i> Dashboard</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="admin/productos.php"><i class="fas fa-boxes me-1"></i> Administrar</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="admin/pedidos.php"><i class="fas fa-clipboard-list me-1"></i> Pedidos</a>
                </li>
                <?php endif; ?>
            </ul>
            
            <div class="d-flex align-items-center">
                <!-- Icono del carrito -->
                <?php if(isset($_SESSION['usuario_id']) && $_SESSION['usuario_rol'] == 'cliente'): ?>
                <a href="carrito.php" class="btn btn-outline-primary position-relative me-3">
                    <i class="fas fa-shopping-cart"></i>
                    <?php if(isset($_SESSION['carrito']) && count($_SESSION['carrito']) > 0): ?>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                        <?php echo count($_SESSION['carrito']); ?>
                    </span>
                    <?php endif; ?>
                </a>
                <?php endif; ?>
                
                <!-- Menú de usuario mejorado -->
                <?php if(isset($_SESSION['usuario_id'])): ?>
                <div class="dropdown position-relative">
                    <a href="#" class="d-flex align-items-center text-decoration-none dropdown-toggle" id="dropdownUser" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="me-2 d-none d-sm-inline"><?php echo htmlspecialchars($_SESSION['usuario_nombre']); ?></span>
                        <i class="fas fa-user-circle fs-4"></i>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="dropdownUser" style="z-index: 1050;">
                        <li><a class="dropdown-item" href="<?php echo ($_SESSION['usuario_rol'] == 'admin') ? 'admin/perfil.php' : 'perfil.php'; ?>"><i class="fas fa-user me-2"></i> Mi perfil</a></li>
                        <?php if($_SESSION['usuario_rol'] == 'cliente'): ?>
                        <li><a class="dropdown-item" href="pedidos.php"><i class="fas fa-clipboard-list me-2"></i> Mis pedidos</a></li>
                        <?php elseif($_SESSION['usuario_rol'] == 'admin'): ?>
                        <li><a class="dropdown-item" href="admin/pedidos.php"><i class="fas fa-clipboard-list me-2"></i> Pedidos</a></li>
                        <?php endif; ?>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i> Cerrar sesión</a></li>
                    </ul>
                </div>
                <?php else: ?>
                <div class="d-flex">
                    <a href="login.php" class="btn btn-outline-primary me-2"><i class="fas fa-sign-in-alt me-1"></i> Ingresar</a>
                    <a href="registro.php" class="btn btn-primary"><i class="fas fa-user-plus me-1"></i> Registrarse</a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<style>
    /* Estilos adicionales para el dropdown */
    .dropdown-menu {
        position: absolute;
        z-index: 1050;
        margin-top: 0.5rem;
        border: 1px solid rgba(0,0,0,.15);
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
    }
    
    .dropdown-item {
        padding: 0.5rem 1.5rem;
        display: flex;
        align-items: center;
        transition: all 0.2s;
    }
    
    .dropdown-item:hover {
        background-color: #f8f9fa;
        color: #0d6efd;
    }
    
    .dropdown-item i {
        width: 20px;
        text-align: center;
        margin-right: 0.5rem;
    }
    
    /* Asegurar que el navbar tenga un z-index mayor que el contenido */
    .navbar {
        position: relative;
        z-index: 1100;
    }
</style>