<?php ?>
<html>
<head>
<script type="text/javascript" src="https://code.jquery.com/jquery-2.2.4.min.js"></script>
<link href="https://fonts.googleapis.com/css?family=Montserrat:400,700|Teko:700&display=swap&subset=latin-ext" rel="stylesheet">
<link rel="stylesheet" href="assets/css/styles.css" />
</head>
<body>
<form id="ajax" method="post" action="search.php">
    <div class="field">
        <h4 class="searchLabel">Chercher une unité, une équipe ou un service administratif :</h3>
        <input type="text" id="centre" name="centre" placeholder="ex : CRAL" required>
    </div>
</form>
<div id="results"></div>
<script type="text/javascript">
$(document).ready(function(){
    $('#ajax').submit(ajax);
})
function ajax(){
        $.ajax({
            url : 'search.php',
            type : 'POST',
            data : $('form').serialize(),
            success: function(data){
                $('#results').html(data);
            }
        });
        return false;
}
window.onload=function(){
    setInterval(ajax, 5000);
}    
</script>
</body>
</html>