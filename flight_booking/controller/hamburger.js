console.log("JS LOADED");

document.addEventListener("DOMContentLoaded", function () {
  const hamburger = document.querySelector(".hamburger");
  const dropdownContent = document.querySelector(".dropdown-content");

  if (!hamburger || !dropdownContent) {
    console.error("Elements not found");
    return;
  }

  hamburger.addEventListener("click", function (e) {
    e.stopPropagation();
    dropdownContent.classList.toggle("show");
  });

  document.addEventListener("click", function () {
    dropdownContent.classList.remove("show");
  });
});
