var vidActive;
var vidActiveRef;
var intervalRewind;
var playback;

function videoRewind(rewindSpeed) {    
	console.log("rewind speed="+rewindSpeed);
	clearInterval(intervalRewind);
	var startSystemTime = new Date().getTime();
	var startVideoTime = vidActive.currentTime();
	intervalRewind = setInterval(function(){
		vidActive.playbackRate(1);
		if(vidActive.currentTime() == 0){
			clearInterval(intervalRewind);
			vidActive.pause();
		}
		else {
			var elapsed = new Date().getTime()-startSystemTime;
			newTime = Math.max(startVideoTime - elapsed*rewindSpeed/1000.0, 0);
			vidActive.currentTime(newTime);
			
		}
	}, 30);
}

function videoPlay(video){
	setVidActiveRef(video);
	vidActive=video.player;
	// for some reason this isn't always happening
	jQuery(video).children('.vjs-poster').attr('style','display:none');
	vidActive.play();
	clearInterval(intervalRewind);
	vidActive.playbackRate(1);
	playback='forward';
}

function videoPause(video){
	// set vidActiveRef
	setVidActiveRef(video);
	vidActive=video.player;
	vidActive.pause();
	vidActive.playbackRate(0);
	clearInterval(intervalRewind);
	playback='';
}

function videoPlayPause(video){
	if(jQuery(video).hasClass('vjs-playing')){
		videoPause(vidActive);
	}
	else if(jQuery(vidActive).hasClass('vjs-paused')){
		videoPlay(vidActive);
	}
}
function setVidActiveRef(video){
	videoId=video.id;
	vidActiveRef=videoId.replace("introvideo",'');
}
