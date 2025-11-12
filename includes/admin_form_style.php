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
  border:1px solid var(--button-border, rgba(var(--accent-pop-rgb, 118,255,101),0.35));
  background:var(--button-bg, rgba(var(--accent-pop-rgb, 118,255,101),0.1));
  color:var(--button-color, rgba(210,255,215,0.9));
  font-weight:700;
  cursor:pointer;
  transition:var(--transition, all 0.25s ease);
}
form button:hover,
form button:focus-visible {
  background:var(
    --button-hover-bg,
    linear-gradient(132deg, rgba(42,217,119,0.34), rgba(118,255,101,0.26))
  );
  color:var(--button-hover-color, #041104);
  box-shadow:var(
    --button-hover-shadow,
    0 18px 36px rgba(17,123,69,0.26), inset 0 0 22px rgba(118,255,101,0.24)
  );
  transform:var(--button-hover-transform, translateY(-3px) scale(1.02));
  border-color:var(--button-hover-border, rgba(42,217,119,0.6));
  outline:none;
}
.success {
  background:rgba(var(--accent-rgb, 42,217,119),0.12);
  border:1px solid rgba(var(--accent-rgb, 42,217,119),0.38);
  color:rgba(var(--accent-pop-rgb, 118,255,101),0.85);
  padding:10px;
  border-radius:8px;
  margin-bottom:15px;
  text-align:center;
}
.back-link {
  display:inline-block;
  margin-top:20px;
  color:var(--accent, #2ad977);
  text-decoration:none;
  font-weight:bold;
}
.back-link:hover {
  text-shadow:0 0 10px rgba(var(--accent-pop-rgb, 118,255,101),0.65);
}
</style>
