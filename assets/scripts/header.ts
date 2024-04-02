import './searchbar'


const burgerBtn = document.querySelector('.burger');
const menu = document.querySelector('.menu-xs');

burgerBtn.addEventListener('click', (event: MouseEvent) => {
    menu.classList.toggle('hidden');
});
