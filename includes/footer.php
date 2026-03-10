<?php
// Reusable footer included across the site
?>
<!-- Site Footer -->
<style>
    .site-footer {
        background: #f8f9fa;
        border-top: 1px solid #e9ecef;
        color: #495057;
        padding: 18px 16px;
        text-align: center;
        font-size: 0.95rem;
        margin-top: 30px;
    }
    .site-footer .footer-inner {
        max-width: 1100px;
        margin: 0 auto;
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: center;
        gap: 8px;
    }
    .site-footer a {
        color: #3F51B5;
        text-decoration: none;
        font-weight: 600;
    }
    .site-footer a:hover { text-decoration: underline; }
    @media (max-width: 700px) {
        .site-footer .footer-inner { flex-direction: column; gap: 6px; }
    }
</style>
<footer class="site-footer">
    <div class="footer-inner">
        <div class="footer-left">&copy; <?php echo date('Y'); ?> <strong>VetControl</strong></div>
        <div class="footer-right">Diseñado por tu clínica • <a href="#">Política de privacidad</a></div>
    </div>
</footer>