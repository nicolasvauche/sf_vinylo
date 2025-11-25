import './stimulus_bootstrap.js';
import './styles/app.scss';

document.addEventListener("turbo:load", () => {
    const modal = document.getElementById("app-modal");
    if (!modal) return;

    modal.addEventListener("modal:confirm", (e) => {
        console.log("CONFIRM EVENT:", e.detail);
    });
});
