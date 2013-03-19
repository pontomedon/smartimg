<!DOCTYPE html>
<html>

<head>
	<meta charset='UTF-8'>
	
	<title>SmartImg on the Seamless Responsive Photo Grid</title>
	
	<link rel='stylesheet' href='css/style.css'>
	
	<script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.9.1/jquery.min.js"></script> 
	<script type="text/javascript" src="js/jquery.smartimg.js"></script> 
	<script type="text/javascript">
		$(document).ready(function() {
			$("#photos").smartimg();
		});
	</script> 
	
</head>


<body>

	<section id="photos">
		<?php 
		for ($i = 0; $i < 48; $i++) {
			echo '<img src="http://placehold.it/100x100" data-src="img/'.$i.'.jpg" >';
		}
		?>
	
	</section>
	
</body>

</html>