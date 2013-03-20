<!DOCTYPE html>
<html>

<head>
	<meta charset='UTF-8'>
	
	<title>SmartImg on the Seamless Responsive Photo Grid</title>
	
	<link rel='stylesheet' href='css/style.css'>
	
	<script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.9.1/jquery.min.js"></script> 
	<script type="text/javascript" src="../src/jquery.smartimg.js"></script> 
	<script type="text/javascript">
		$(document).ready(function() {
			var si = $("#photos").smartimg();

			// add further images
			si.addImages($("#nonrespphotos img"));
		});
	</script> 
	
</head>


<body>

	<section id="photos">
		<?php 
		for ($i = 0; $i < 20; $i++) {
			echo '<img src="http://placehold.it/100x100" class="responsive" data-aspect="1:1" data-src="/demo/img/'.$i.'.jpg" >';
		}
		?>
	</section>
	<section id="nonrespphotos">
		<img src="http://placehold.it/100x100" data-src="/demo/img/0.jpg" >
	</section>
</body>

</html>