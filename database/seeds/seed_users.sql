
If you want a different password, you can generate a new hash in PHP:
  echo password_hash('YourPasswordHere', PASSWORD_BCRYPT);