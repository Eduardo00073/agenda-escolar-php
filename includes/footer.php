<?php
/**
 * Agenda Escolar - Footer Comum (Área Pública)
 */
require_once __DIR__ . '/../config.php';
?>
    </main>

    <!-- Rodapé -->
    <footer class="site-footer">
        <div class="container" style="display: flex; flex-direction: column; align-items: center; gap: 8px;">
            <p>&copy; <?php echo date('Y'); ?> <?php echo SCHOOL_NAME; ?>. Todos os direitos reservados.</p>
            <p style="font-size: 12px; color: var(--text-muted);">
                <a href="https://www.prof-eduardo.com/" target="_blank" style="color: inherit; text-decoration: none; opacity: 0.85;">Desenvolvedor: Eduardo Junior Alcântara da Silva - Professor de TI e desenvolvedor full stack</a>
            </p>
        </div>
    </footer>

    <!-- Bibliotecas Externas adicionais via CDN (IMask para máscaras de telefone) -->
    <script src="https://unpkg.com/imask"></script>

    <!-- JS Principal -->
    <script src="<?php echo BASE_URL; ?>/assets/js/app.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/app.js'); ?>"></script>
    
    <script>
        // Inicializar ícones do Lucide em toda a página
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    </script>
</body>
</html>
