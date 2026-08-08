<?php
/**
 * Agenda Escolar - Footer Administrativo
 */
?>
        </main>
        
        <!-- Rodapé do Painel -->
        <footer style="padding: 20px 30px; border-top: 1px solid var(--border); background-color: var(--bg-card); font-size: 13px; color: var(--text-muted); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
            <div>
                &copy; <?php echo date('Y'); ?> <?php echo SCHOOL_NAME; ?>. Todos os direitos reservados.
            </div>
            <div>
                <a href="https://www.prof-eduardo.com/" target="_blank" style="color: inherit; text-decoration: none; opacity: 0.85;">Desenvolvedor: Eduardo Junior Alcântara da Silva - Professor de TI e desenvolvedor full stack</a>
            </div>
        </footer>
        
    </div> <!-- Fim de .admin-main -->
</div> <!-- Fim de .admin-layout -->

<!-- Script principal -->
<script src="<?php echo BASE_URL; ?>/assets/js/app.js?v=<?php echo filemtime(__DIR__ . '/../../assets/js/app.js'); ?>"></script>

<script>
    // Inicializar ícones do Lucide
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
</script>
</body>
</html>
