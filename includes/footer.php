<?php
declare(strict_types=1);
?>
</div>
<footer class="app-footer">
    <div class="footer-brand"><span class="footer-badge">STALK &amp; STABLE</span><span>&copy; <?= date('Y') ?> All Rights Reserved</span></div>
    <div class="footer-right"><span class="support-pill"><i class="fas fa-headset"></i> Distribution Management System</span><span>Powered by <strong>FlexiScript Labs.</strong></span></div>
</footer>
<style>
.app-footer{margin-left:260px;padding:18px 30px;background:#004a99;color:rgba(255,255,255,.82);display:flex;justify-content:space-between;align-items:center;gap:18px;font-family:Inter,Arial,sans-serif;font-size:12px;border-top:1px solid rgba(255,255,255,.1);box-shadow:0 -4px 15px rgba(0,0,0,.08)}
.footer-brand,.footer-right{display:flex;align-items:center;gap:12px;flex-wrap:wrap}.footer-badge{background:rgba(255,255,255,.15);color:#fff;padding:6px 12px;border-radius:4px;font-weight:900;letter-spacing:1px;font-size:10px;border:1px solid rgba(255,255,255,.25)}.support-pill{background:rgba(0,0,0,.18);border:1px solid rgba(255,255,255,.18);padding:6px 12px;border-radius:50px;color:#fff;font-weight:700}.support-pill i{margin-right:5px}.footer-right strong{color:#fff}.footer-right{justify-content:flex-end}
@media(max-width:768px){.app-footer{margin-left:0;padding:18px 20px;flex-direction:column;text-align:center}.footer-brand,.footer-right{justify-content:center}}
</style>
<script>
document.addEventListener("DOMContentLoaded",function(){const path=window.location.pathname.split("/").pop();document.querySelectorAll(".sidebar a").forEach(l=>{if(l.getAttribute("href")===path){l.style.backgroundColor="rgba(255,255,255,.2)";l.style.color="#fff";l.style.fontWeight="bold";l.style.borderLeft="4px solid #fff";l.classList.add("active")}})});
</script>
</body>
</html>