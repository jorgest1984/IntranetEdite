const fs = require('fs');
let html = fs.readFileSync('documentacion.php', 'utf8');

// Replace suffix logic
let newSuffixLogic = "let suffix = type === 'recibi' ? '' : (type === 'bienvenida' ? '_bienvenida' : (type === 'didactica' ? 'Didactica' : (type === 'informe' ? 'Informe' : (type === 'tutorias' ? 'Tutorias' : (type === 'acta' ? 'Acta' : (type === 'informe_alumno' ? 'InformeAlumno' : (type === 'diploma' ? 'Diploma' : (type === 'xml' ? 'Xml' : (type === 'xml_encuestas' ? 'XmlEnc' : 'Anexo')))))))));";

html = html.replace(/let suffix = type === 'recibi' \? '' : \(type === 'didactica' \? 'Didactica' : \(type === 'informe' \? 'Informe' : \(type === 'tutorias' \? 'Tutorias' : \(type === 'acta' \? 'Acta' : \(type === 'informe_alumno' \? 'InformeAlumno' : \(type === 'diploma' \? 'Diploma' : \(type === 'xml' \? 'Xml' : \(type === 'xml_encuestas' \? 'XmlEnc' : 'Anexo'\)\)\)\)\)\)\)\);/g, newSuffixLogic);

// Replace modalId logic
let newModalIdLogic = `let modalId = type === 'recibi' ? 'docModal' : 
                 (type === 'bienvenida' ? 'bienvenidaModal' : 
                 (type === 'didactica' ? 'didacticaModal' : 
                 (type === 'informe' ? 'informeModal' : 
                 (type === 'tutorias' ? 'tutoriasModal' : 
                 (type === 'acta' ? 'actaModal' : 
                 (type === 'informe_alumno' ? 'informeAlumnoModal' : 
                 (type === 'diploma' ? 'diplomaModal' : 
                 (type === 'xml' ? 'xmlModal' : 
                 (type === 'xml_encuestas' ? 'xmlEncuestasModal' : 'anexoModal')))))))));`;

html = html.replace(/let modalId = type === 'recibi' \? 'docModal' : \n                 \(type === 'didactica' \? 'didacticaModal' : \n                 \(type === 'informe' \? 'informeModal' : \n                 \(type === 'tutorias' \? 'tutoriasModal' : \n                 \(type === 'acta' \? 'actaModal' : \n                 \(type === 'informe_alumno' \? 'informeAlumnoModal' : \n                 \(type === 'diploma' \? 'diplomaModal' : \n                 \(type === 'xml' \? 'xmlModal' : \n                 \(type === 'xml_encuestas' \? 'xmlEncuestasModal' : 'anexoModal'\)\)\)\)\)\)\)\);/g, newModalIdLogic);

fs.writeFileSync('documentacion.php', html, 'utf8');
console.log('Fixed documentacion.php');
