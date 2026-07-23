const fs = require('fs');
let content = fs.readFileSync('pdf_hoja_bienvenida.php', 'utf8');
content = content.replace(/•/g, '-');
fs.writeFileSync('pdf_hoja_bienvenida.php', content, 'utf8');
console.log('Replaced bullets with hyphens.');
