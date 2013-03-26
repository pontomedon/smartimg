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
			selector				:'img',
			src						:'data-src',
			imghandler				:'/src/smarting.php',
			resizeThreshold			:80,
			numberOfImgPerReq		:5,
			respMarkerClass			:'.responsive',
			isImagesLoadedIncluded	:false,
			cbInitload				:function(){},
			
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
		 *  responsive and non responsive images within the container 
		 */
		var respImageSet;
		var fixedImageSet;
		
		var container = $(this);
		
		// split image set into resp. and non responsive images
		filterImageSet(container);
		
		
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
				requestBatches(respImageSet);
			}
		});
		
		
		/*
		 * LOAD EVENT
		 */		
		$(window).on('load', function() {
			var respPromiseQueue = requestBatches(respImageSet);
			var fixedPromiseQueue = requestBatches(fixedImageSet);
			var promiseQueue = respPromiseQueue.concat(fixedPromiseQueue);
			
			var joinedImageSet = respImageSet.add(fixedImageSet);
			
			/*
			 * all async requests are synced using the jquery deferred approach
			 */
			$.when.apply(this, promiseQueue).then(function() {
				
				// all batches returned and images are replaced
				// note: at this point we dont know if the images are loaded!
				if(settings.isImagesLoadedIncluded){
					// use imagesloaded plugin to detect loaded images
					joinedImageSet.imagesLoaded(function(){
						// call user defined cb
						settings.cbInitload.call();
					});
				}
			});
			
		});
		
		function filterImageSet(imageContainer){
			// whole image set
			var imageSet = imageContainer.find(settings.selector);
			
			// filter responive set
			respImageSet = imageSet.filter(settings.respMarkerClass);
			// filter images not handled as responsive
			fixedImageSet = imageSet.not(settings.respMarkerClass);
		}
		
		
		/**
		 * REQUEST NEW IMAGES USING BATCH PROCESSING
		 */
		function requestBatches(images){
			
			var startIdx = 0;
			var endIdx = 0;					
			
			var reqList = [];
			
			// iterate over set using the number of images per request
			for(i=0; i < Math.ceil(images.length/settings.numberOfImgPerReq) ; i++){
				
				startIdx = i*settings.numberOfImgPerReq;
				endIdx = startIdx + settings.numberOfImgPerReq;
				
				// select subset
				var requestImages = images.slice(startIdx,endIdx);
				// generate array for request
				var requestArray = createReqArray(requestImages);

				/*
				 * process async request for batch
				 * note: to keep references between requestImages and requestArray
				 * the images are copied into the request context -> indices match
				 */

				var reqPromise = $.ajax({
										url: settings.imghandler,
										cache: true,
										data: {	method: 'getImage',
												arg: JSON.stringify(requestArray)},
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
				reqList.push(reqPromise);
			}
			return reqList;
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
				// absolute path to original image src
				idxMap[idx]["src"] = image.attr("data-src");
				// desired width of the image
				idxMap[idx]["width"] = image.width();
				
				// optional aspect attribute 
				var aspect = image.attr("data-aspect");
				if (aspect !== undefined)
					idxMap[idx]["aspect"] = aspect;				
				
				delete image;
				// TODO: retina displays
			});			
			return idxMap;
		}
		
		
		
		/**
		 * ADD IMAGES TO SET
		 * @param elements newImages	one or more elements to add
		 */
		var addImages = function(newImages){
			// note: according to http://api.jquery.com/add/ add() generates a new set
			// hence, the indices also change according to the order in the document
	
			var newRespImages = newImages.filter(settings.respMarkerClass);
			var newFixedImages = newImages.not(settings.respMarkerClass);
			
			// filter responive set
			respImageSet = respImageSet.add(newRespImages);
			// filter images not handled as responsive
			fixedImageSet = fixedImageSet.add(newFixedImages);
			
			// trigger load function for new images
			requestBatches(newRespImages);
			requestBatches(newFixedImages);
		
		};	
		
		/**
		 * REMOVE IMAGES FROM SET
		 * @param elements imagesToRemove	one or more elements to remove
		 */
		var removeImages = function(imagesToRemove){
			// filter responive set
			respImageSet = respImageSet.not(imagesToRemove.filter(settings.respMarkerClass));
			// filter images not handled as responsive
			fixedImageSet = fixedImageSet.not(imagesToRemove.not(settings.respMarkerClass));
		};		
		
		
		return {
			addImages: addImages,
			removeImages: removeImages
        }
	};
})( jQuery );