<?php
// Google Search Console, Google Tag Manager and Meta Pixel (storefront pages only)
const PK_GTM_ID   = 'GTM-MKZ5XQ3D';
const PK_PIXEL_ID = '1354603696469186';
$pkCustomer = tracking_customer();
// Pixel Advanced Matching — fbq hashes these in the browser before sending to Meta
$pkMatch = array_filter([
    'em'          => $pkCustomer['email'],
    'ph'          => $pkCustomer['phone'],
    'fn'          => $pkCustomer['first'],
    'ln'          => $pkCustomer['last'],
    'external_id' => $pkCustomer['id'],
    'country'     => $pkCustomer['logged_in'] ? 'bd' : '',
]);
?>
<meta name="google-site-verification" content="755l4Awwcvdb-wwy_aeU4cX3E_wD_hSE0r5yvGgwA9A" />
<!-- Google Tag Manager -->
<script>window.dataLayer = window.dataLayer || [];
dataLayer.push(<?= json_encode([
    'customer_logged_in' => $pkCustomer['logged_in'],
    'customer_id'        => $pkCustomer['id'],
    'customer_name'      => $pkCustomer['name'],
    'customer_email'     => $pkCustomer['email'],
    'customer_phone'     => $pkCustomer['phone'],
], JSON_UNESCAPED_UNICODE) ?>);
(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
})(window,document,'script','dataLayer','<?= PK_GTM_ID ?>');</script>
<!-- End Google Tag Manager -->
<!-- Meta Pixel Code -->
<script>
!function(f,b,e,v,n,t,s)
{if(f.fbq)return;n=f.fbq=function(){n.callMethod?
n.callMethod.apply(n,arguments):n.queue.push(arguments)};
if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
n.queue=[];t=b.createElement(e);t.async=!0;
t.src=v;s=b.getElementsByTagName(e)[0];
s.parentNode.insertBefore(t,s)}(window, document,'script',
'https://connect.facebook.net/en_US/fbevents.js');
fbq('init', '<?= PK_PIXEL_ID ?>'<?= $pkMatch ? ', ' . json_encode($pkMatch, JSON_UNESCAPED_UNICODE) : '' ?>);
fbq('track', 'PageView');
</script>
<noscript><img height="1" width="1" style="display:none"
src="https://www.facebook.com/tr?id=<?= PK_PIXEL_ID ?>&ev=PageView&noscript=1"
/></noscript>
<!-- End Meta Pixel Code -->
