<?php
require_once 'config.php';
require_once __DIR__ . '/includes/whatsapp-widget.php';

// Función para limpiar datos de entrada
function limpiarDatos($dato) {
    $dato = trim($dato);
    $dato = stripslashes($dato);
    $dato = htmlspecialchars($dato);
    return $dato;
}

// Obtener parámetros de búsqueda y filtrado
$busqueda = isset($_GET['busqueda']) ? limpiarDatos($_GET['busqueda']) : '';
$categoria = isset($_GET['categoria_id']) ? limpiarDatos($_GET['categoria_id']) : '';
$orden = isset($_GET['orden']) ? limpiarDatos($_GET['orden']) : 'recientes';

// Construir consulta SQL con filtros
$sql = "SELECT p.*, 
       (SELECT SUM(stock) FROM producto_tallas pt WHERE pt.producto_id = p.id) as stock_total
       FROM productos p WHERE 1=1";
$params = [];

if(!empty($busqueda)) {
    $sql .= " AND (p.nombre LIKE ? OR p.descripcion LIKE ?)";
    $params[] = "%$busqueda%";
    $params[] = "%$busqueda%";
}

if(!empty($categoria)) {
    $sql .= " AND p.categoria = ?";
    $params[] = $categoria;
}

// Solo mostrar productos con stock disponible
$sql .= " AND EXISTS (SELECT 1 FROM producto_tallas pt WHERE pt.producto_id = p.id AND pt.stock > 0)";

// Aplicar ordenamiento
switch($orden) {
    case 'precio_asc':
        $sql .= " ORDER BY COALESCE(p.precio_descuento, p.precio_normal) ASC";
        break;
    case 'precio_desc':
        $sql .= " ORDER BY COALESCE(p.precio_descuento, p.precio_normal) DESC";
        break;
    case 'destacados':
        $sql .= " ORDER BY p.destacado DESC, p.fecha_creacion DESC";
        break;
    default: // 'recientes'
        $sql .= " ORDER BY p.fecha_creacion DESC";
        break;
}

// Paginación
$pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$por_pagina = 12;
$inicio = ($pagina > 1) ? ($pagina * $por_pagina - $por_pagina) : 0;

$sql_paginada = $sql . " LIMIT $inicio, $por_pagina";

// Obtener productos
$stmt = $conn->prepare($sql_paginada);
$stmt->execute($params);
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener total de productos para paginación
$sql_total = "SELECT COUNT(*) as total FROM productos p WHERE 1=1";
if(!empty($busqueda)) {
    $sql_total .= " AND (p.nombre LIKE ? OR p.descripcion LIKE ?)";
}
if(!empty($categoria)) {
    $sql_total .= " AND p.categoria_id = ?";
}
$sql_total .= " AND EXISTS (SELECT 1 FROM producto_tallas pt WHERE pt.producto_id = p.id AND pt.stock > 0)";

$stmt_total = $conn->prepare($sql_total);
$stmt_total->execute($params);
$total_productos = $stmt_total->fetch()['total'];
$paginas = ceil($total_productos / $por_pagina);

// Obtener categorías para el filtro
$stmt_categorias = $conn->query("SELECT DISTINCT categoria_id FROM productos WHERE categoria_id IS NOT NULL AND categoria_id != ''");
$categorias = $stmt_categorias->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Productos - Kalo's Style</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="includes/asesoria.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Estilos Generales */
        :root {
        --primary-color: #000000;
        --secondary-color: #6c757d;
        --accent-color: #ff6b6b;
        --light-color: #f8f9fa;
        --dark-color: #343a40;
        --success-color: #28a745;
        --danger-color: #dc3545;
        --warning-color: #ffc107;
        --info-color: #17a2b8;
        }

        body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        color: #333;
        line-height: 1.6;
        background-color: #f5f5f5;
        }

        a {
        color: var(--primary-color);
        text-decoration: none;
        transition: color 0.3s ease;
        }

        a:hover {
        color: var(--accent-color);
        }

        /* Header y Navegación */
        .navbar {
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .navbar-brand {
        font-weight: 700;
        font-size: 1.5rem;
        }

        .navbar-brand img {
        height: 40px;
        }

        .nav-link {
        font-weight: 500;
        }

        .cart-count {
        position: relative;
        }

        .cart-badge {
        position: absolute;
        top: -5px;
        right: -10px;
        background-color: var(--accent-color);
        color: white;
        border-radius: 50%;
        width: 20px;
        height: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.7rem;
        }

        /* Banner Principal */
        .hero-banner {
        background-image: 
            linear-gradient(to bottom, rgba(0, 0, 0, 0.6), rgba(0, 0, 0, 0.6)),
            url('assets/img/logo/banner2.jpg');
        background-position: center center;
        background-repeat: no-repeat;
        background-size: cover;
        min-height: 500px;
        display: flex;
        align-items: center;
        color: white;
        position: relative;
        margin-bottom: 3rem;
        }

        .banner-content {
        z-index: 2;
        }

        .banner-title {
        font-size: 3.5rem;
        font-weight: 700;
        margin-bottom: 1rem;
        text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.5);
        }

        .banner-subtitle {
        font-size: 1.5rem;
        margin-bottom: 2rem;
        text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.5);
        }

        /* Productos */
        .product-card {
        border: none;
        border-radius: 10px;
        overflow: hidden;
        transition: all 0.3s ease;
        background: white;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        margin-bottom: 30px;
        height: 100%;
        }

        .product-card:hover {
        transform: translateY(-10px);
        box-shadow: 0 15px 30px rgba(0, 0, 0, 0.2);
        }

        .product-img-container {
        height: 250px;
        overflow: hidden;
        position: relative;
        }

        .product-img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.5s ease;
        }

        .product-card:hover .product-img {
        transform: scale(1.05);
        }

        .product-badge {
        position: absolute;
        top: 10px;
        right: 10px;
        background-color: var(--accent-color);
        color: white;
        padding: 5px 10px;
        border-radius: 50px;
        font-size: 0.8rem;
        font-weight: 600;
        }

        .product-info {
        padding: 20px;
        }

        .product-title {
        font-weight: 600;
        margin-bottom: 0.5rem;
        height: 50px;
        overflow: hidden;
        }

        .product-price {
        font-weight: 700;
        color: var(--primary-color);
        font-size: 1.2rem;
        }

        .old-price {
        text-decoration: line-through;
        color: var(--secondary-color);
        font-size: 0.9rem;
        margin-left: 5px;
        }

        .discount-badge {
        background-color: var(--success-color);
        color: white;
        padding: 3px 8px;
        border-radius: 5px;
        font-size: 0.8rem;
        margin-left: 10px;
        }

        /* Botones */
        .btn {
        border-radius: 50px;
        padding: 10px 25px;
        font-weight: 600;
        transition: all 0.3s ease;
        }

        .btn-primary {
        background-color: var(--primary-color);
        border-color: var(--primary-color);
        }

        .btn-primary:hover {
        background-color: #333;
        border-color: #333;
        transform: translateY(-2px);
        }

        .btn-outline-primary {
        color: var(--primary-color);
        border-color: var(--primary-color);
        }

        .btn-outline-primary:hover {
        background-color: var(--primary-color);
        color: white;
        }

        .btn-accent {
        background-color: var(--accent-color);
        color: white;
        }

        .btn-accent:hover {
        background-color: #e05555;
        color: white;
        transform: translateY(-2px);
        }

        /* Secciones */
        .section-title {
        position: relative;
        margin-bottom: 2rem;
        padding-bottom: 10px;
        }

        .section-title:after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 0;
        width: 50px;
        height: 3px;
        background-color: var(--accent-color);
        }

        /* Footer */
        .footer {
        background-color: var(--dark-color);
        color: white;
        padding: 3rem 0;
        }

        .footer-links h5 {
        margin-bottom: 1.5rem;
        position: relative;
        }

        .footer-links h5:after {
        content: '';
        position: absolute;
        bottom: -10px;
        left: 0;
        width: 30px;
        height: 2px;
        background-color: var(--accent-color);
        }

        .footer-links ul {
        list-style: none;
        padding: 0;
        }

        .footer-links li {
        margin-bottom: 0.5rem;
        }

        .footer-links a {
        color: #adb5bd;
        }

        .footer-links a:hover {
        color: white;
        }

        .social-icons a {
        display: inline-block;
        width: 40px;
        height: 40px;
        background-color: rgba(255, 255, 255, 0.1);
        border-radius: 50%;
        text-align: center;
        line-height: 40px;
        margin-right: 10px;
        color: white;
        transition: all 0.3s ease;
        }

        .social-icons a:hover {
        background-color: var(--accent-color);
        transform: translateY(-3px);
        }

        /* Responsive Design */
        @media (max-width: 1200px) {
        .hero-banner {
            min-height: 450px;
        }
        
        .banner-title {
            font-size: 3rem;
        }
        }

        @media (max-width: 992px) {
        .hero-banner {
            min-height: 400px;
            text-align: center;
        }
        
        .banner-title {
            font-size: 2.5rem;
        }
        
        .banner-subtitle {
            font-size: 1.3rem;
        }
        
        .product-img-container {
            height: 200px;
        }
        }

        @media (max-width: 768px) {
        .hero-banner {
            min-height: 350px;
            background-position: 60% center;
        }
        
        .banner-title {
            font-size: 2rem;
        }
        
        .banner-subtitle {
            font-size: 1.1rem;
        }
        
        .navbar-brand {
            font-size: 1.3rem;
        }
        
        .navbar-brand img {
            height: 30px;
        }
        }

        @media (max-width: 576px) {
        .hero-banner {
            min-height: 300px;
        }
        
        .banner-title {
            font-size: 1.8rem;
        }
        
        .product-img-container {
            height: 180px;
        }
        
        .btn {
            padding: 8px 20px;
            font-size: 0.9rem;
        }
        }

        /* Animaciones */
        @keyframes fadeIn {
        from {
            opacity: 0;
        }
        to {
            opacity: 1;
        }
        }

        @keyframes slideInUp {
        from {
            transform: translateY(50px);
            opacity: 0;
        }
        to {
            transform: translateY(0);
            opacity: 1;
        }
        }

        .fade-in {
        animation: fadeIn 0.6s ease forwards;
        }

        .slide-in-up {
        animation: slideInUp 0.6s ease forwards;
        }

        /* Efectos hover */
        .hover-scale {
        transition: transform 0.3s ease;
        }

        .hover-scale:hover {
        transform: scale(1.03);
        }

        /* Utilidades */
        .rounded-lg {
        border-radius: 15px;
        }

        .shadow-sm {
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .shadow-lg {
        box-shadow: 0 15px 30px rgba(0, 0, 0, 0.2);
        }

        /* Formularios */
        .form-control {
        border-radius: 50px;
        padding: 12px 20px;
        border: 1px solid #ced4da;
        }

        .form-control:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 0.2rem rgba(0, 0, 0, 0.1);
        }

        /* Alertas */
        .alert {
        border-radius: 10px;
        padding: 15px 20px;
        }

        .alert-success {
        background-color: rgba(40, 167, 69, 0.1);
        border-left: 4px solid var(--success-color);
        }

        .alert-danger {
        background-color: rgba(220, 53, 69, 0.1);
        border-left: 4px solid var(--danger-color);
        }

        /* Paginación */
        .pagination .page-item.active .page-link {
        background-color: var(--primary-color);
        border-color: var(--primary-color);
        }

        .pagination .page-link {
        color: var(--primary-color);
        border-radius: 50% !important;
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 5px;
        border: none;
        }

        .pagination .page-link:hover {
        background-color: #f0f0f0;
        }

        /* Carrito de compras */
        .cart-item {
        border-bottom: 1px solid #eee;
        padding: 15px 0;
        }

        .cart-item-img {
        width: 80px;
        height: 80px;
        object-fit: cover;
        border-radius: 10px;
        }

        /* Checkout */
        .checkout-steps .step {
        position: relative;
        padding-bottom: 15px;
        }

        .checkout-steps .step.active {
        color: var(--primary-color);
        font-weight: 600;
        }

        .checkout-steps .step.completed {
        color: var(--success-color);
        }

        .checkout-steps .step:not(:last-child):after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 0;
        width: 100%;
        height: 2px;
        background-color: #eee;
        }

        .checkout-steps .step.active:after,
        .checkout-steps .step.completed:after {
        background-color: var(--success-color);
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <?php include 'navbar.php'; ?>
    
    <section class="hero-banner">
        <div class="container">
            <div class="row">
                <div class="col-lg-6">
                    <div class="banner-content slide-in-up">
                        <h1 class="banner-title">Descubre Nuestra Colección</h1>
                        <p class="banner-subtitle">Productos exclusivos con la mejor calidad para tu estilo</p>
                        <a href="productos.php" class="btn btn-accent">Ver productos</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Filtros y ordenamiento -->
    <section class="container my-5">
        <div class="row">
            <div class="col-md-3 filter-section">
                <div class="card filter-card shadow-sm">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">Filtrar Productos</h5>
                    </div>
                    <div class="card-body">
                        <form id="filtroForm" method="GET" action="productos.php">
                            <div class="mb-3">
                                <label for="busqueda" class="form-label">Buscar</label>
                                <input type="text" class="form-control" id="busqueda" name="busqueda" value="<?php echo htmlspecialchars($busqueda); ?>">
                            </div>
                            
                            <?php if(!empty($categorias)): ?>
                                <div class="mb-3">
                                    <label for="categoria" class="form-label">Categoría</label>
                                    <select class="form-select" id="categoria" name="categoria">
                                        <option value="">Todas las categorías</option>
                                        <?php foreach($categorias as $cat): ?>
                                            <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $categoria == $cat ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($cat); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php endif; ?>
                            
                            <div class="mb-3">
                                <label for="orden" class="form-label">Ordenar por</label>
                                <select class="form-select" id="orden" name="orden">
                                    <option value="recientes" <?php echo $orden == 'recientes' ? 'selected' : ''; ?>>Más recientes</option>
                                    <option value="destacados" <?php echo $orden == 'destacados' ? 'selected' : ''; ?>>Destacados</option>
                                    <option value="precio_asc" <?php echo $orden == 'precio_asc' ? 'selected' : ''; ?>>Precio: menor a mayor</option>
                                    <option value="precio_desc" <?php echo $orden == 'precio_desc' ? 'selected' : ''; ?>>Precio: mayor a menor</option>
                                </select>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100">Aplicar Filtros</button>
                            <?php if($busqueda || $categoria || $orden != 'recientes'): ?>
                                <a href="productos.php" class="btn btn-outline-secondary w-100 mt-2">Limpiar Filtros</a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Listado de productos -->
            <div class="col-md-9">
                <?php if(empty($productos)): ?>
                    <div class="alert alert-info text-center">
                        No se encontraron productos con los filtros seleccionados.
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach($productos as $producto): ?>
                            <div class="col-lg-4 col-md-6 mb-4">
                                <div class="product-card card h-100">
                                    <?php if($producto['destacado']): ?>
                                        <span class="badge badge-destacado">Destacado</span>
                                    <?php endif; ?>
                                    
                                    <?php if($producto['precio_descuento'] && $producto['precio_descuento'] < $producto['precio_normal']): ?>
                                        <?php 
                                        $descuento = round(($producto['precio_normal'] - $producto['precio_descuento']) / $producto['precio_normal'] * 100);
                                        ?>
                                        <span class="badge bg-danger position-absolute top-0 start-0 m-2">-<?php echo $descuento; ?>%</span>
                                    <?php endif; ?>
                                    
                                    <img src="<?php echo PRODUCT_IMAGES_PATH . htmlspecialchars($producto['imagen']); ?>" 
                                        class="card-img-top product-img" 
                                        alt="<?php echo htmlspecialchars($producto['nombre']); ?>"
                                        onerror="this.src='<?php echo ASSETS_PATH; ?>img/placeholder.jpg'">
                                    
                                    <div class="card-body">
                                        <h5 class="card-title"><?php echo htmlspecialchars($producto['nombre']); ?></h5>
                                        <p class="card-text text-muted"><?php echo htmlspecialchars(substr($producto['descripcion'], 0, 80)) . '...'; ?></p>
                                        
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="price fw-bold">$<?php echo number_format($producto['precio_descuento'] ?: $producto['precio_normal'], 0, ',', '.'); ?></span>
                                            <?php if($producto['precio_descuento']): ?>
                                                <span class="old-price text-muted">$<?php echo number_format($producto['precio_normal'], 0, ',', '.'); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <!-- Mostrar disponibilidad general -->
                                        <div class="stock-info">
                                            <?php if($producto['stock_total'] > 0): ?>
                                                <span class="text-success"><i class="fas fa-check-circle"></i> Disponible</span>
                                            <?php else: ?>
                                                <span class="text-danger"><i class="fas fa-times-circle"></i> Agotado</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="card-footer bg-white border-top-0">
                                        <div class="d-grid gap-2">
                                            <a href="detalles-producto.php?id=<?php echo $producto['id']; ?>" class="btn btn-outline-primary">
                                                <i class="fas fa-eye me-1"></i> Ver detalle
                                            </a>
                                            <?php if(isset($_SESSION['usuario_id']) && $producto['stock_total'] > 0): ?>
                                                <button class="btn btn-primary btn-agregar-carrito" 
                                                        data-producto-id="<?php echo $producto['id']; ?>"
                                                        data-producto-nombre="<?php echo htmlspecialchars($producto['nombre']); ?>"
                                                        data-producto-precio="<?php echo $producto['precio_descuento'] ?: $producto['precio_normal']; ?>"
                                                        data-producto-imagen="<?php echo htmlspecialchars($producto['imagen']); ?>">
                                                </button>
                                            <?php elseif(!isset($_SESSION['usuario_id'])): ?>
                                            <?php else: ?>
                                                <button class="btn btn-secondary" disabled>
                                                    <i class="fas fa-times-circle me-1"></i> Agotado
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Paginación -->
                    <?php if($paginas > 1): ?>
                        <nav aria-label="Page navigation" class="mt-4">
                            <ul class="pagination justify-content-center">
                                <?php if($pagina > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="productos.php?<?php echo http_build_query(array_merge($_GET, ['pagina' => $pagina - 1])); ?>" aria-label="Previous">
                                            <span aria-hidden="true">&laquo;</span>
                                        </a>
                                    </li>
                                <?php endif; ?>
                                
                                <?php for($i = 1; $i <= $paginas; $i++): ?>
                                    <li class="page-item <?php echo $pagina == $i ? 'active' : ''; ?>">
                                        <a class="page-link" href="productos.php?<?php echo http_build_query(array_merge($_GET, ['pagina' => $i])); ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if($pagina < $paginas): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="productos.php?<?php echo http_build_query(array_merge($_GET, ['pagina' => $pagina + 1])); ?>" aria-label="Next">
                                            <span aria-hidden="true">&raquo;</span>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>
    
    <!-- Modal para selección de talla -->
    <div class="modal fade" id="tallaModal" tabindex="-1" aria-labelledby="tallaModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="tallaModalLabel">Selecciona tu talla</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="tallaOptions" class="talla-options">
                        <!-- Las opciones de talla se cargarán aquí mediante JavaScript -->
                    </div>
                    <div class="mb-3">
                        <label for="cantidad" class="form-label">Cantidad:</label>
                        <input type="number" id="cantidad" class="form-control" value="1" min="1" max="10">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" id="btnConfirmarTalla" class="btn btn-primary">Confirmar</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    <?php include 'footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Actualizar formulario cuando cambian los selects
        document.getElementById('categoria').addEventListener('change', function() {
            document.getElementById('filtroForm').submit();
        });
        
        document.getElementById('orden').addEventListener('change', function() {
            document.getElementById('filtroForm').submit();
        });
        
        // Variables globales para el modal de tallas
        let productoActual = null;
        const tallaModal = new bootstrap.Modal(document.getElementById('tallaModal'));
        
        // Manejar clic en botón "Añadir al carrito"
        document.querySelectorAll('.btn-agregar-carrito').forEach(btn => {
            btn.addEventListener('click', function() {
                productoActual = {
                    id: this.getAttribute('data-producto-id'),
                    nombre: this.getAttribute('data-producto-nombre'),
                    precio: parseFloat(this.getAttribute('data-producto-precio')),
                    imagen: this.getAttribute('data-producto-imagen')
                };
                
                // Obtener tallas disponibles para este producto
                fetch(`obtener-tallas.php?producto_id=${productoActual.id}`)
                    .then(response => response.json())
                    .then(tallas => {
                        const tallaOptions = document.getElementById('tallaOptions');
                        tallaOptions.innerHTML = '';
                        
                        if(tallas.length === 0) {
                            tallaOptions.innerHTML = '<p class="text-muted">Este producto no tiene tallas disponibles.</p>';
                            document.getElementById('btnConfirmarTalla').disabled = true;
                        } else {
                            tallas.forEach(talla => {
                                const btnTalla = document.createElement('button');
                                btnTalla.type = 'button';
                                btnTalla.className = `btn btn-talla ${talla.stock > 0 ? '' : 'sin-stock'}`;
                                btnTalla.textContent = talla.nombre;
                                btnTalla.dataset.tallaId = talla.id;
                                btnTalla.dataset.stock = talla.stock;
                                
                                if(talla.stock > 0) {
                                    btnTalla.addEventListener('click', function() {
                                        // Deseleccionar todas las tallas
                                        document.querySelectorAll('.btn-talla').forEach(btn => {
                                            btn.classList.remove('selected', 'btn-primary');
                                            btn.classList.add('btn-outline-secondary');
                                        });
                                        
                                        // Seleccionar esta talla
                                        this.classList.add('selected', 'btn-primary');
                                        this.classList.remove('btn-outline-secondary');
                                        productoActual.talla_id = this.dataset.tallaId;
                                        productoActual.talla_nombre = this.textContent;
                                        
                                        // Actualizar cantidad máxima
                                        const inputCantidad = document.getElementById('cantidad');
                                        inputCantidad.max = this.dataset.stock;
                                        if(parseInt(inputCantidad.value) > parseInt(inputCantidad.max)) {
                                            inputCantidad.value = inputCantidad.max;
                                        }
                                    });
                                } else {
                                    btnTalla.title = 'Sin stock disponible';
                                }
                                
                                tallaOptions.appendChild(btnTalla);
                            });
                            
                            document.getElementById('btnConfirmarTalla').disabled = false;
                        }
                        
                        // Mostrar el modal
                        tallaModal.show();
                    })
                    .catch(error => {
                        console.error('Error al obtener tallas:', error);
                        alert('Error al obtener las tallas disponibles');
                    });
            });
        });
        
        // Confirmar selección de talla
        document.getElementById('btnConfirmarTalla').addEventListener('click', function() {
            if(!productoActual || !productoActual.talla_id) {
                alert('Por favor selecciona una talla');
                return;
            }
            
            const cantidad = parseInt(document.getElementById('cantidad').value);
            if(isNaN(cantidad) || cantidad < 1) {
                alert('Por favor ingresa una cantidad válida');
                return;
            }
            
            // Agregar producto al carrito
            fetch('agregar-carrito.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    producto_id: productoActual.id,
                    talla_id: productoActual.talla_id,
                    cantidad: cantidad,
                    nombre: productoActual.nombre,
                    precio: productoActual.precio,
                    imagen: productoActual.imagen,
                    talla_nombre: productoActual.talla_nombre
                })
            })
            .then(response => response.json())
            .then(data => {
                if(data.success) {
                    // Actualizar contador del carrito
                    const cartBadge = document.querySelector('.cart-badge');
                    if(cartBadge) {
                        cartBadge.textContent = data.carrito_count;
                    } else {
                        // Si no existe el badge, crearlo
                        const cartCount = document.querySelector('.cart-count');
                        if(cartCount) {
                            const badge = document.createElement('span');
                            badge.className = 'cart-badge';
                            badge.textContent = data.carrito_count;
                            cartCount.appendChild(badge);
                        }
                    }
                    
                    // Mostrar mensaje de éxito
                    alert('Producto agregado al carrito');
                    
                    // Cerrar modal
                    tallaModal.hide();
                } else {
                    alert(data.message || 'Error al agregar al carrito');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error al agregar al carrito');
            });
        });
    </script>
</body>
</html>