// Small usability helpers, loaded on every page. Nothing here is
// required for the app to work — if this file failed to load, every
// form and link would still function exactly as before.

document.addEventListener("DOMContentLoaded", function () {
  makeAlertsDismissible();
  wrapWideTables();
  addCommentCounter();
  preventDoubleSubmit();
});

// The red/green <p> messages already printed by PHP get a close
// button, and the green ones fade out on their own after a bit.
function makeAlertsDismissible() {
  var alerts = document.querySelectorAll(
    'p[style*="color: red"], p[style*="color:red"], p[style*="color: green"], p[style*="color:green"]',
  );

  alerts.forEach(function (alert) {
    var closeBtn = document.createElement("button");
    closeBtn.type = "button";
    closeBtn.className = "alert-close";
    closeBtn.textContent = "\u00D7";
    closeBtn.onclick = function () {
      alert.remove();
    };
    alert.appendChild(closeBtn);

    var isSuccess = (alert.getAttribute("style") || "").indexOf("green") !== -1;
    if (isSuccess) {
      setTimeout(function () {
        alert.remove();
      }, 4000);
    }
  });
}

// Tables can get wide (Admin has a lot of columns) — wrap them so
// they scroll sideways on a small screen instead of breaking layout.
function wrapWideTables() {
  document.querySelectorAll("table").forEach(function (table) {
    var wrapper = document.createElement("div");
    wrapper.className = "table-wrap";
    table.parentNode.insertBefore(wrapper, table);
    wrapper.appendChild(table);
  });
}

// The evaluation comment box is the only textarea with a maxlength,
// so this just quietly does nothing on every other page.
function addCommentCounter() {
  var textarea = document.querySelector("textarea[maxlength]");
  if (!textarea) return;

  var max = parseInt(textarea.getAttribute("maxlength"), 10);
  var counter = document.createElement("span");
  counter.className = "char-count";
  textarea.insertAdjacentElement("afterend", counter);

  function update() {
    var left = max - textarea.value.length;
    counter.textContent = textarea.value.length + " / " + max;
    counter.classList.toggle("low", left < 50);
  }
  textarea.addEventListener("input", update);
  update();
}

// Stop a slow connection (or an impatient double-click) from
// submitting the same form twice.
function preventDoubleSubmit() {
  document.querySelectorAll("form").forEach(function (form) {
    form.addEventListener("submit", function () {
      var btn = form.querySelector("button");
      if (!btn || btn.disabled) return;
      btn.disabled = true;
      btn.textContent = "Submitting...";
    });
  });
}
