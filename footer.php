    <div id="footer">
        <p>Maintained by Timotheus' family. Messages are reviewed, printed, and delivered to him.</p>
        <p>Questions: <span id="obEmail"></span>
        <noscript>
            <?php
            // Split email for noscript fallback
            $eml = $admin_email;
            $eml_parts = explode('@', $eml);
            if (count($eml_parts) === 2) {
                $user = htmlspecialchars($eml_parts[0], ENT_QUOTES, 'UTF-8');
                $domain = htmlspecialchars($eml_parts[1], ENT_QUOTES, 'UTF-8');
                $domain_parts = explode('.', $domain);
                $domain_obf = implode(' [dot] ', $domain_parts);
                echo $user . ' [at] ' . $domain_obf;
            } else {
                echo htmlspecialchars($admin_email, ENT_QUOTES, 'UTF-8');
            }
            ?>
        </noscript>
        </p>
        <script>
        // Email obfuscation using PHP values
        (function() {
            var u = <?php echo json_encode($eml_parts[0]); ?>;
            var d = <?php echo json_encode($eml_parts[1]); ?>;
            var e = u + '@' + d;
            var a = document.createElement('a');
            a.href = 'mailto:' + e;
            a.textContent = e;
            document.getElementById('obEmail').appendChild(a);
        })();
        </script>
        <p>&copy; <?php echo date('Y'); ?> | Powered by BellaBook</p>
    </div>
</div>
</body>
</html>