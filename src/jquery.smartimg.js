/**
 * SMARTIMG
 */

(function( $ ) {
	$.fn.smartimg = function(options) {

		/*
		 * SETTINGS
		 * default settings are extended by user provided settings
		 * - selector: the jquery selector for the images
		 * - src: the attribute holding the reference to the origninal image
		 * - imghandler: path to the image handler (the php script)  
		 */
		var settings = $.extend( {
			'selector'			:'img',
			'src'				:'data-src',
			'imghandler'		:'/src/smarting.php',
			'resizeThreshold'	:80,
			'numberOfImgPerReq'	:5,
		}, options);

		/*
		 * DEVICE RELATED BREAKPOINTS
		 * the script triggers if one of these breakpoints is reached
		 */
		var deviceResolutionBreakpoints = [320, 480, 768, 1024];
		
		/*
		 * store initial window width as reference for resizing operations
		 */
		var windowWidthRef = $(window).width();
		
		/*
		 * select images within the container 
		 */
		var imageSet = $(this).find(settings.selector);
			
		
		
		/*
		 * RESIZE EVENT
		 * send requests only if the window is resized by a specific amount (>resizeThreshold) 
		 */		
		$(window).on('resize', function() {
			// TODO: trigger at device breakpoints!
			
			// check if difference exceeds the threshold
			if(Math.abs(windowWidthRef - $(window).width()) >= settings.resizeThreshold ){
				// store new width as reference
				windowWidthRef = $(window).width();
				// trigger requests
				requestBatches();				
			}
		});
		
		
		/*
		 * LOAD EVENT
		 */		
		$(window).on('load', function() {
			requestBatches();
		});
		
		
		/**
		 * ADD IMAGES TO SET
		 * @param elements newImages	one or more elements to add
		 */
		function addImages(newImages){
			// note: according to http://api.jquery.com/add/ add() generates a new set
			// hence, the indices also change according to the order in the document
			
			//TODO: test behavior
			imageSet = imageSet.add(newImages);
		}		
		
		/**
		 * REMOVE IMAGES FROM SET
		 * @param elements imagesToRemove	one or more elements to remove
		 */
		function removeImages(imagesToRemove){
			//TODO: test behavior
			imageSet = imageSet.not(newImages);
		}		
		
		/**
		 * REQUEST NEW IMAGES USING BATCH PROCESSING
		 */
		function requestBatches(){
			var startIdx = 0;
			var endIdx = 0;
			
			// iterate over set using the number of images per request
			for(i=0; i < Math.ceil(imageSet.length/settings.numberOfImgPerReq) ; i++){
				
				startIdx = i*settings.numberOfImgPerReq;
				endIdx = startIdx + settings.numberOfImgPerReq;
				
				// select subset
				var requestImages = imageSet.slice(startIdx,endIdx);
				// generate array for request
				var requestArray = createReqArray(requestImages);

				/*
				 * process async request for batch
				 * note: to keep references between requestImages and requestArray
				 * the images are copied into the request context -> indices match
				 */
				$.ajax({
					url: settings.imghandler,
					cache: true,
					data: {images: JSON.stringify(requestArray)},
					type: 'POST',
					dataType: 'json',
					async: true,
					context:{	images:	requestImages},
					
					// callback for results
					success: function(data){		
						var img;
						// iterate over results
						// note: indices have to match at this point!
						for (var i in data){
							
							// get image from set
							img = this.images.eq(i);
							
							// replace images from context if required (src has changed)
							if(img.attr("src") != data[i]["src"]){
								img.attr("src",data[i]["src"]);
							}								
						}
					},					
				});
				
			}
			
		}
		
		/**
		 * CREATE ARRAY FOR REQUEST 
		 * the resulting array has the syntax
		 * [idx]
		 * 		[src] - path to the original image
		 * 		[width] - requested width
		 * @param array images	jquery object representing the set of images
		 */
		function createReqArray(images){
			var idxMap = new Object();
			
			images.each(function(idx){ 
				var image = $(this);
				idxMap[idx] = new Object();
				idxMap[idx]["src"] = image.attr("data-src");
				idxMap[idx]["width"] = image.width();
				
				// TODO: retina displays
			});			
			return idxMap;
		}
	};
})( jQuery );