<style>
main {
  padding: 120px 50px;
  max-width: 900px;
  margin: 0 auto;
}
form {
  background: rgba(25, 25, 25, 0.95);
  border: 1px solid rgba(57,255,20,0.5);
  border-radius: 15px;
  padding: 30px;
  box-shadow: 0 0 25px rgba(57,255,20,0.3);
}
form label {
  display:block;
  margin-top:15px;
  font-weight:bold;
  color:#76ff65;
}
form input, form textarea {
  width:100%;
  margin-top:8px;
  padding:10px;
  border:none;
  border-radius:8px;
  background:#111;
  color:#fff;
  font-size:1rem;
}
form textarea {
  min-height:200px;
  resize:vertical;
}
form button {
  margin-top:20px;
  display:inline-flex;
  align-items:center;
  justify-content:center;
  gap:8px;
  padding:12px 25px;
  border-radius:12px;
  border:1px solid var(--button-border, rgba(57,255,20,0.35));
  background:var(--button-bg, rgba(57,255,20,0.1));
  color:var(--button-color, rgba(210,255,215,0.9));
  font-weight:700;
  cursor:pointer;
  transition:var(--transition, all 0.25s ease);
}
form button:hover {
  background:var(--button-hover-bg, linear-gradient(90deg,#39ff14,#76ff65));
  color:var(--button-hover-color, #041104);
  box-shadow:var(--button-hover-shadow, 0 18px 32px rgba(57,255,20,0.32));
  transform:var(--button-hover-transform, translateY(-1px));
}
.success {
  background:rgba(40,255,120,0.1);
  border:1px solid rgba(40,255,120,0.4);
  color:#90ee90;
  padding:10px;
  border-radius:8px;
  margin-bottom:15px;
  text-align:center;
}
.back-link {
  display:inline-block;
  margin-top:20px;
  color:#39ff14;
  text-decoration:none;
  font-weight:bold;
}
.back-link:hover {
  text-shadow:0 0 10px #39ff14;
}
</style>
