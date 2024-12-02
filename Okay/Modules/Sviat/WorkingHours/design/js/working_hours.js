document.addEventListener("DOMContentLoaded", function () {
    const trigger = document.getElementById("working-hours-trigger");
    const list = document.getElementById("working-hours-list");

    // Показати/сховати при кліку
    trigger.addEventListener("click", function () {
        list.classList.toggle("visible");
        trigger.classList.toggle("opened");
    });

    // Сховати при кліку за межами
    document.addEventListener("click", function (e) {
        if (!trigger.contains(e.target) && !list.contains(e.target)) {
            list.classList.remove("visible");
            trigger.classList.remove("opened");
        }
    });

    // Відобразити при наведенні (за бажанням)
    trigger.addEventListener("mouseover", function () {
        list.classList.add("visible");
    });
    trigger.addEventListener("mouseout", function () {
        list.classList.remove("visible");
    });
});
