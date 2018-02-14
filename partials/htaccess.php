RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteCond %{REQUEST_URI} <?php echo "^/$upload_dir"; ?> 
RewriteCond %{REQUEST_URI} \.(jpg|jpeg|png|gif)$
RewriteRule . <?php echo "/$plugin_dir/proxy.php"; ?> [L]
