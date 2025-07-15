const signup = document.getElementById("signup");
const login = document.getElementById("login");
const signButton = document.getElementById("signup-button");
const loginButton = document.getElementById("login-button");

// your existing click‑handlers
signButton.addEventListener('click', function(){
  signup.style.display = "none";
  login.style.display  = "block";
});
loginButton.addEventListener('click', function(){
  signup.style.display = "block";
  login.style.display  = "none";
});

window.addEventListener('DOMContentLoaded', () => {
  const wantLogin = window.location.hash === '#login' || new URLSearchParams(window.location.search).get('status') === 'invalid_data';

  if (wantLogin) {
    signup.style.display = "none";
    login.style.display  = "block";
  }
});
