document.addEventListener("DOMContentLoaded", function () {
  var fullScreenOnButton = document.getElementById("full_screen_on");
  var fullScreenOffButton = document.getElementById("full_screen_off");

  function enterFullscreen() {
    var element = document.documentElement;

    if (element.requestFullscreen) {
      element.requestFullscreen();
    } else if (element.msRequestFullscreen) {
      element.msRequestFullscreen();
    } else if (element.mozRequestFullScreen) {
      element.mozRequestFullScreen();
    } else if (element.webkitRequestFullscreen) {
      element.webkitRequestFullscreen(Element.ALLOW_KEYBOARD_INPUT);
    }
  }

  function exitFullscreen() {
    if (document.exitFullscreen) {
      document.exitFullscreen();
    } else if (document.msExitFullscreen) {
      document.msExitFullscreen();
    } else if (document.mozCancelFullScreen) {
      document.mozCancelFullScreen();
    } else if (document.webkitExitFullscreen) {
      document.webkitExitFullscreen();
    }
  }

  function updateButtons() {
    var isFullscreen =
      document.fullscreenElement ||
      document.mozFullScreenElement ||
      document.webkitFullscreenElement ||
      document.msFullscreenElement;

    if (isFullscreen) {
      // В режиме полноэкранного режима
      fullScreenOnButton.classList.add("hidden");
      fullScreenOffButton.classList.remove("hidden");
    } else {
      // В обычном режиме
      fullScreenOnButton.classList.remove("hidden");
      fullScreenOffButton.classList.add("hidden");
    }
  }

  fullScreenOnButton.addEventListener("click", function () {
    enterFullscreen();
    updateButtons();
  });

  fullScreenOffButton.addEventListener("click", function () {
    exitFullscreen();
    updateButtons();
  });

  document.addEventListener("keydown", function (event) {
    // Нажата клавиша F11
    if (event.key === "F11") {
      event.preventDefault();
      toggleFullscreen();
      updateButtons();
    }
  });

  function toggleFullscreen() {
    var isFullscreen =
      document.fullscreenElement ||
      document.mozFullScreenElement ||
      document.webkitFullscreenElement ||
      document.msFullscreenElement;

    if (!isFullscreen) {
      enterFullscreen();
    } else {
      exitFullscreen();
    }
  }

  document.addEventListener("fullscreenchange", updateButtons);
  document.addEventListener("mozfullscreenchange", updateButtons);
  document.addEventListener("webkitfullscreenchange", updateButtons);
  document.addEventListener("msfullscreenchange", updateButtons);
});
