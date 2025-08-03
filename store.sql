-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1:3306
-- Tiempo de generación: 03-08-2025 a las 04:40:01
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `store`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `carrito`
--

CREATE TABLE `carrito` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `producto_id` int(11) NOT NULL,
  `talla_id` int(11) DEFAULT NULL,
  `cantidad` int(11) NOT NULL DEFAULT 1,
  `fecha_agregado` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_expiracion` timestamp NOT NULL DEFAULT (current_timestamp() + interval 3 day)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `categorias`
--

CREATE TABLE `categorias` (
  `categoria_id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `slug` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `categorias`
--

INSERT INTO `categorias` (`categoria_id`, `nombre`, `descripcion`, `slug`) VALUES
(3, 'Camisetas', 'Camisetas modernas y casuales para hombre', 'camisetas'),
(4, 'Camisas', 'Camisas formales e informales para hombre', 'camisas'),
(5, 'Pantalones', 'Pantalones modernos y cómodos para hombre', 'pantalones'),
(6, 'Jeans', 'Jeans de moda para hombre', 'jeans'),
(7, 'Chaquetas', 'Chaquetas de moda para hombre', 'chaquetas'),
(8, 'Sudaderas', 'Sudaderas deportivas y urbanas para hombre', 'sudaderas'),
(9, 'Bermudas', 'Bermudas y shorts casuales para hombre', 'bermudas'),
(10, 'Ropa deportiva', 'Ropa deportiva funcional y moderna para hombre', 'ropa-deportiva'),
(11, 'Accesorios', 'Accesorios de moda para hombre', 'accesorios'),
(12, 'Zapatos', 'Zapatos modernos para hombre', 'zapatos'),
(13, 'Blusas', 'Blusas modernas y versátiles para mujer', 'blusas'),
(14, 'Camisetas mujer', 'Camisetas con estilo para mujer', 'camisetas-mujer'),
(15, 'Faldas', 'Faldas modernas y elegantes para mujer', 'faldas'),
(16, 'Vestidos', 'Vestidos de moda casual y formal para mujer', 'vestidos'),
(17, 'Pantalones mujer', 'Pantalones de corte moderno para mujer', 'pantalones-mujer'),
(18, 'Jeans mujer', 'Jeans de diferentes estilos para mujer', 'jeans-mujer'),
(19, 'Chaquetas mujer', 'Chaquetas modernas y cómodas para mujer', 'chaquetas-mujer'),
(20, 'Sudaderas mujer', 'Sudaderas con diseño actual para mujer', 'sudaderas-mujer'),
(21, 'Ropa deportiva mujer', 'Ropa deportiva para mujer con estilo', 'ropa-deportiva-mujer'),
(22, 'Zapatos mujer', 'Zapatos y sandalias de moda para mujer', 'zapatos-mujer'),
(23, 'Accesorios mujer', 'Accesorios modernos para complementar el look', 'accesorios-mujer');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `favoritos`
--

CREATE TABLE `favoritos` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `producto_id` int(11) NOT NULL,
  `fecha_agregado` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `historial_pedidos`
--

CREATE TABLE `historial_pedidos` (
  `id` int(11) NOT NULL,
  `pedido_id` int(11) NOT NULL,
  `estado` varchar(20) NOT NULL,
  `comentario` text DEFAULT NULL,
  `fecha` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `inventario`
--

CREATE TABLE `inventario` (
  `id` int(11) NOT NULL,
  `producto_id` int(11) NOT NULL,
  `cantidad` int(11) NOT NULL,
  `tipo` enum('entrada','salida','ajuste') NOT NULL,
  `razon` varchar(255) DEFAULT NULL,
  `fecha` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pedidos`
--

CREATE TABLE `pedidos` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `direccion_envio` varchar(255) DEFAULT NULL,
  `metodo_pago` varchar(20) DEFAULT NULL,
  `notas` text DEFAULT NULL,
  `fecha_pago` datetime DEFAULT NULL,
  `fecha_envio` datetime(6) DEFAULT current_timestamp(6),
  `estado_pago` varchar(20) DEFAULT 'pendiente',
  `fecha_pedido` timestamp NOT NULL DEFAULT current_timestamp(),
  `estado` enum('pendiente','procesando','enviado','completado','cancelado') DEFAULT 'pendiente',
  `total` decimal(10,2) DEFAULT NULL,
  `fecha_cancelacion` datetime DEFAULT NULL,
  `motivo_cancelacion` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `pedidos`
--

INSERT INTO `pedidos` (`id`, `usuario_id`, `direccion_envio`, `metodo_pago`, `notas`, `fecha_pago`, `fecha_envio`, `estado_pago`, `fecha_pedido`, `estado`, `total`, `fecha_cancelacion`, `motivo_cancelacion`) VALUES
(8, 2, 'Chiquinquira', 'efectivo', NULL, NULL, '2025-08-02 09:29:41.504123', 'pendiente', '2025-08-02 13:26:50', 'cancelado', 160000.00, '2025-08-02 09:42:25', NULL),
(9, 2, 'Chiquinquira', 'efectivo', NULL, NULL, '2025-08-02 09:29:41.504123', 'pendiente', '2025-08-02 13:58:28', 'enviado', 320000.00, NULL, NULL),
(10, 2, 'Chiquinquira', 'efectivo', 'cerca de la panaderia', NULL, '2025-08-02 16:57:17.798583', 'pendiente', '2025-08-02 21:57:17', 'enviado', 90000.00, NULL, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pedido_items`
--

CREATE TABLE `pedido_items` (
  `id` int(11) NOT NULL,
  `pedido_id` int(11) NOT NULL,
  `producto_id` int(11) NOT NULL,
  `cantidad` int(11) NOT NULL,
  `precio_unitario` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `talla_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `pedido_items`
--

INSERT INTO `pedido_items` (`id`, `pedido_id`, `producto_id`, `cantidad`, `precio_unitario`, `subtotal`, `talla_id`) VALUES
(9, 8, 10, 1, 160000.00, 160000.00, NULL),
(10, 9, 10, 2, 160000.00, 320000.00, 60),
(11, 10, 12, 1, 75000.00, 75000.00, 60);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `productos`
--

CREATE TABLE `productos` (
  `id` int(11) NOT NULL,
  `nombre` varchar(255) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `precio_normal` decimal(10,2) NOT NULL,
  `precio_descuento` decimal(10,2) DEFAULT NULL,
  `categoria_id` int(11) DEFAULT NULL,
  `imagen` varchar(255) DEFAULT NULL,
  `imagenes_adicionales` varchar(255) DEFAULT NULL,
  `destacado` tinyint(1) DEFAULT 0,
  `stock` int(11) NOT NULL DEFAULT 0,
  `sku` varchar(50) DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `categoria_talla` varchar(20) NOT NULL DEFAULT 'camisa' COMMENT 'Tipo de talla: camisa, pantalon, zapato',
  `descuento_inicio` datetime DEFAULT NULL,
  `descuento_fin` datetime DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `productos`
--

INSERT INTO `productos` (`id`, `nombre`, `descripcion`, `precio_normal`, `precio_descuento`, `categoria_id`, `imagen`, `imagenes_adicionales`, `destacado`, `stock`, `sku`, `fecha_creacion`, `categoria_talla`, `descuento_inicio`, `descuento_fin`, `activo`) VALUES
(10, 'ZAPATOS', 'Original de charlie', 160000.00, NULL, 12, 'product_688b90219b3b80.12798414.jpeg', '[\"product_extra_688e1deca1bca3.53136291.jpeg\"]', 1, 10, '003', '2025-07-31 15:47:45', 'camisa', NULL, NULL, 1),
(12, 'Pantalon', 'calidad', 80000.00, 75000.00, 12, 'product_688d62ff63c8c5.29858082.jpg', '[\"product_extra_688e5531566166.06610347.jpg\"]', 1, 10, '002', '2025-08-02 00:59:43', 'camisa', '2025-08-02 21:47:32', '2025-08-05 21:47:32', 1),
(13, 'Camiseta Oversize', 'Alto gramaje\r\nCuello Rib', 65000.00, 40000.00, 12, 'product_688e28d57a2c42.66454613.jpg', '[\"product_extra_688e28d57a8ba3.95596509.jpg\"]', 1, 10, '001', '2025-08-02 15:03:49', 'camisa', '2025-08-02 21:47:43', '2025-08-05 21:47:43', 1),
(14, 'Camiseta Oversize', '', 65000.00, 50000.00, 12, 'product_688e393c2f6f54.56496871.jpg', '[\"product_extra_688e393c2f9547.03877653.jpg\"]', 1, 0, '004', '2025-08-02 16:13:48', 'camisa', '2025-08-02 21:46:45', '2025-08-05 21:46:45', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `producto_imagenes`
--

CREATE TABLE `producto_imagenes` (
  `id` int(11) NOT NULL,
  `producto_id` int(11) NOT NULL,
  `imagen` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `producto_tallas`
--

CREATE TABLE `producto_tallas` (
  `producto_id` int(11) NOT NULL,
  `talla_id` int(11) NOT NULL,
  `stock` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `producto_tallas`
--

INSERT INTO `producto_tallas` (`producto_id`, `talla_id`, `stock`) VALUES
(10, 60, 3),
(10, 61, 5),
(10, 62, 5),
(10, 63, 1),
(10, 64, 1),
(12, 60, 1),
(12, 62, 4),
(12, 64, 1),
(12, 66, 5),
(12, 68, 3),
(12, 70, 6),
(13, 2, 5),
(13, 3, 5),
(13, 4, 1),
(13, 5, 1),
(14, 2, 3),
(14, 3, 3),
(14, 4, 5),
(14, 5, 2);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tallas`
--

CREATE TABLE `tallas` (
  `id` int(11) NOT NULL,
  `nombre` varchar(10) NOT NULL,
  `orden` int(11) DEFAULT 0,
  `categoria_talla` varchar(20) NOT NULL DEFAULT 'camisa' COMMENT 'Tipo de talla: camisa/jeans/zapato'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `tallas`
--

INSERT INTO `tallas` (`id`, `nombre`, `orden`, `categoria_talla`) VALUES
(1, 'XS', 1, 'zapato'),
(2, 'S', 2, 'zapato'),
(3, 'M', 3, 'zapato'),
(4, 'L', 4, 'zapato'),
(5, 'XL', 5, 'zapato'),
(6, 'XXL', 6, 'zapato'),
(7, 'XXXL', 7, 'zapato'),
(45, '28', 8, 'jeans'),
(46, '30', 9, 'jeans'),
(47, '32', 10, 'zapato'),
(48, '34', 11, 'zapato'),
(49, '36', 12, 'jeans'),
(50, '38', 13, 'jeans'),
(51, '40', 14, 'jeans'),
(52, '42', 15, 'jeans'),
(53, '44', 16, 'jeans'),
(60, '36', 17, 'zapato'),
(61, '37', 18, 'zapato'),
(62, '38', 19, 'zapato'),
(63, '39', 20, 'zapato'),
(64, '40', 21, 'zapato'),
(65, '41', 22, 'zapato'),
(66, '42', 23, 'zapato'),
(67, '43', 24, 'zapato'),
(68, '44', 25, 'zapato'),
(69, '45', 26, 'zapato'),
(70, '46', 27, 'zapato');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `direccion` text DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `rol` enum('admin','cliente') DEFAULT 'cliente',
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id`, `nombre`, `email`, `password`, `direccion`, `telefono`, `rol`, `fecha_registro`) VALUES
(1, 'Gerlin Victor', 'admin@gmail.com', '$2y$10$ATAutY97zbPLFb/HLgE1FuRyvMM5xP2l6uFNoTBw5cFsgzFd9nv.u', 'Barrio chipre', '3228495116', 'admin', '2025-07-29 21:07:38'),
(2, 'Juan Villalobo', 'prueba1@gmail.com', '$2y$10$Io/U.6hmS5RFhGaZf1eYYOc9RJ/OSJfHwkW33KDh4mVmXiCONmER6', 'Chiquinquira', '3228495116', 'cliente', '2025-07-30 14:40:44');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `carrito`
--
ALTER TABLE `carrito`
  ADD PRIMARY KEY (`id`),
  ADD KEY `producto_id` (`producto_id`),
  ADD KEY `talla_id` (`talla_id`),
  ADD KEY `idx_usuario` (`usuario_id`),
  ADD KEY `idx_expiracion` (`fecha_expiracion`);

--
-- Indices de la tabla `categorias`
--
ALTER TABLE `categorias`
  ADD PRIMARY KEY (`categoria_id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Indices de la tabla `favoritos`
--
ALTER TABLE `favoritos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `usuario_producto` (`usuario_id`,`producto_id`),
  ADD KEY `producto_id` (`producto_id`);

--
-- Indices de la tabla `historial_pedidos`
--
ALTER TABLE `historial_pedidos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pedido_id` (`pedido_id`);

--
-- Indices de la tabla `inventario`
--
ALTER TABLE `inventario`
  ADD PRIMARY KEY (`id`),
  ADD KEY `producto_id` (`producto_id`);

--
-- Indices de la tabla `pedidos`
--
ALTER TABLE `pedidos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `usuario_id` (`usuario_id`);

--
-- Indices de la tabla `pedido_items`
--
ALTER TABLE `pedido_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pedido_id` (`pedido_id`),
  ADD KEY `producto_id` (`producto_id`),
  ADD KEY `fk_pedido_items_tallas` (`talla_id`);

--
-- Indices de la tabla `productos`
--
ALTER TABLE `productos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_productos_categorias` (`categoria_id`);

--
-- Indices de la tabla `producto_imagenes`
--
ALTER TABLE `producto_imagenes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `producto_id` (`producto_id`);

--
-- Indices de la tabla `producto_tallas`
--
ALTER TABLE `producto_tallas`
  ADD PRIMARY KEY (`producto_id`,`talla_id`),
  ADD KEY `fk_talla` (`talla_id`);

--
-- Indices de la tabla `tallas`
--
ALTER TABLE `tallas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_nombre_categoria` (`nombre`,`categoria_talla`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `carrito`
--
ALTER TABLE `carrito`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `categorias`
--
ALTER TABLE `categorias`
  MODIFY `categoria_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT de la tabla `favoritos`
--
ALTER TABLE `favoritos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT de la tabla `historial_pedidos`
--
ALTER TABLE `historial_pedidos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `inventario`
--
ALTER TABLE `inventario`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `pedidos`
--
ALTER TABLE `pedidos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT de la tabla `pedido_items`
--
ALTER TABLE `pedido_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT de la tabla `productos`
--
ALTER TABLE `productos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT de la tabla `producto_imagenes`
--
ALTER TABLE `producto_imagenes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `tallas`
--
ALTER TABLE `tallas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=75;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `carrito`
--
ALTER TABLE `carrito`
  ADD CONSTRAINT `carrito_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `carrito_ibfk_2` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `carrito_ibfk_3` FOREIGN KEY (`talla_id`) REFERENCES `tallas` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `favoritos`
--
ALTER TABLE `favoritos`
  ADD CONSTRAINT `favoritos_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`),
  ADD CONSTRAINT `favoritos_ibfk_2` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`);

--
-- Filtros para la tabla `historial_pedidos`
--
ALTER TABLE `historial_pedidos`
  ADD CONSTRAINT `historial_pedidos_ibfk_1` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`);

--
-- Filtros para la tabla `inventario`
--
ALTER TABLE `inventario`
  ADD CONSTRAINT `inventario_ibfk_1` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`);

--
-- Filtros para la tabla `pedidos`
--
ALTER TABLE `pedidos`
  ADD CONSTRAINT `pedidos_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`);

--
-- Filtros para la tabla `pedido_items`
--
ALTER TABLE `pedido_items`
  ADD CONSTRAINT `fk_pedido_items_tallas` FOREIGN KEY (`talla_id`) REFERENCES `tallas` (`id`),
  ADD CONSTRAINT `pedido_items_ibfk_1` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`),
  ADD CONSTRAINT `pedido_items_ibfk_2` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`);

--
-- Filtros para la tabla `productos`
--
ALTER TABLE `productos`
  ADD CONSTRAINT `fk_productos_categorias` FOREIGN KEY (`categoria_id`) REFERENCES `categorias` (`categoria_id`);

--
-- Filtros para la tabla `producto_imagenes`
--
ALTER TABLE `producto_imagenes`
  ADD CONSTRAINT `producto_imagenes_ibfk_1` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `producto_tallas`
--
ALTER TABLE `producto_tallas`
  ADD CONSTRAINT `fk_producto` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_talla` FOREIGN KEY (`talla_id`) REFERENCES `tallas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `producto_tallas_ibfk_1` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `producto_tallas_ibfk_2` FOREIGN KEY (`talla_id`) REFERENCES `tallas` (`id`) ON DELETE CASCADE;

DELIMITER $$
--
-- Eventos
--
CREATE DEFINER=`root`@`localhost` EVENT `limpiar_carritos_expirados` ON SCHEDULE EVERY 1 DAY STARTS '2025-08-02 16:18:17' ON COMPLETION NOT PRESERVE ENABLE DO DELETE FROM carrito WHERE fecha_expiracion < NOW()$$

DELIMITER ;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
