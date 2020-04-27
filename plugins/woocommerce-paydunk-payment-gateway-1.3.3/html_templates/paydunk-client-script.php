<?php if (!empty($paydunk)): ?>

    <script type="text/javascript">

        if (!ajaxurl) var ajaxurl = "<?php echo admin_url('admin-ajax.php'); ?>";
        var checkoutURL = "<?php echo wc_get_page_permalink('checkout'); ?>";

        var paydunkInit = {
            appID: "<?php echo $paydunk->get_option('app_id') ?>",
            version: "1.0.0"
        };

        window.onload = function() {
            (function(d, s, id, st, r) {
                var js,
                    stylesheet,
                    fjs = d.getElementsByTagName(s)[0];

                // check if element with id="paydunk-jssdk" already exist
                if (d.getElementById(id)) {return;}

                // create new DOM elements
                js = d.createElement(s);
                js.id = id;
                js.src = "https://api.paydunk.com/js/paydunkSignin.js";

                stylesheet = d.createElement(st);
                stylesheet.rel = r;
                stylesheet.href = "https://api.paydunk.com/css/login.css";

                // insert DOM elements
                fjs.parentNode.insertBefore(js, fjs);
                fjs.parentNode.insertBefore(stylesheet, fjs);
            }(document, 'script', 'paydunk-jssdk', 'link', 'stylesheet'));
        }

    </script>
    
<?php endif; ?>