
// Parse PHP Data to JS
const empresaGlobal = 1;
const convocatoriaActiva = 1;
const alumnosAcitvos = 1;

// State management for dynamically loaded data
const loadedData = {
    recibi: {
        alumnos: [],
        context: null
    },
    anexo1: {
        alumnos: [],
        context: null
    },
    didactica: {
        grupos: [],
        context: null
    },
    informe: {
        grupos: [],
        context: null
    },
    tutorias: {
        grupos: [],
        context: null
    },
    acta: {
        grupos: [],
        context: null
    },
    informe_alumno: {
        grupos: [],
        context: null
    },
    diploma: {
        grupos: [],
        context: null
    },
    xml: {
        grupos: [],
        context: null
    },
    xml_encuestas: {
        grupos: [],
        context: null
    }
};

const PRELOAD = {
    convocatoria_id: 1,
    plan_id: 1,
    accion_id: 1,
    grupo_id: 1
};

window.openDocModal = async function(type) {
    let modalId = type === 'recibi' ? 'docModal' : 
                 (type === 'didactica' ? 'didacticaModal' : 
                 (type === 'informe' ? 'informeModal' : 
                 (type === 'tutorias' ? 'tutoriasModal' : 
                 (type === 'acta' ? 'actaModal' : 
                 (type === 'informe_alumno' ? 'informeAlumnoModal' : 
                 (type === 'diploma' ? 'diplomaModal' : 
                 (type === 'xml' ? 'xmlModal' : 
                 (type === 'xml_encuestas' ? 'xmlEncuestasModal' : 'anexoModal'))))))));
                 
    const modal = document.getElementById(modalId);
    if (!modal) return;
    
    modal.classList.add('active');
    
    let suffix = type === 'recibi' ? '' : (type === 'didactica' ? 'Didactica' : (type === 'informe' ? 'Informe' : (type === 'tutorias' ? 'Tutorias' : (type === 'acta' ? 'Acta' : (type === 'informe_alumno' ? 'InformeAlumno' : (type === 'diploma' ? 'Diploma' : (type === 'xml' ? 'Xml' : (type === 'xml_encuestas' ? 'XmlEnc' : 'Anexo'))))))));
    let convSelect = document.getElementById('convocatoriaSelect' + suffix);
    let planSelect = document.getElementById('planSelect' + suffix);
    let accionSelect = document.getElementById('accionSelect' + suffix);
    let grupoSelect = document.getElementById('grupoSelect' + suffix);
    
    if (PRELOAD.convocatoria_id && convSelect) {
        if (convSelect.value == PRELOAD.convocatoria_id && planSelect && planSelect.value == PRELOAD.plan_id) {
            return; // Already preloaded
        }
        
        convSelect.value = PRELOAD.convocatoria_id;
        
        if (PRELOAD.plan_id && planSelect) {
            planSelect.innerHTML = '<option>Cargando...</option>';
            let resP = await fetch(`api_documentos_cascade.php?action=get_planes&convocatoria_id=${PRELOAD.convocatoria_id}`);
            let planes = await resP.json();
            planSelect.innerHTML = '<option value="">-- Selecciona Plan --</option>';
            planes.forEach(p => planSelect.innerHTML += `<option value="${p.id}">${p.codigo ? p.codigo + ' - ' : ''}${p.nombre}</option>`);
            planSelect.disabled = false;
            planSelect.value = PRELOAD.plan_id;
            
            if (PRELOAD.accion_id && accionSelect) {
                accionSelect.innerHTML = '<option>Cargando...</option>';
                let resA = await fetch(`api_documentos_cascade.php?action=get_acciones&plan_id=${PRELOAD.plan_id}`);
                let acciones = await resA.json();
                accionSelect.innerHTML = '<option value="">-- Selecciona Acción Formativa --</option>';
                acciones.forEach(af => accionSelect.innerHTML += `<option value="${af.id}">${af.num_accion ? '#' + af.num_accion + ' - ' : ''}${af.titulo}</option>`);
                accionSelect.disabled = false;
                accionSelect.value = PRELOAD.accion_id;
                
                // Trigger subsequent loaders manually so we can preselect groups if needed
                let isGlobalAlumno = type === 'recibi';
                if (isGlobalAlumno) {
                    loadAlumnos(type, PRELOAD.accion_id);
                }
                
                if (grupoSelect) {
                    grupoSelect.innerHTML = '<option>Cargando...</option>';
                    let resG = await fetch(`api_documentos_cascade.php?action=get_grupos&accion_id=${PRELOAD.accion_id}`);
                    let grupos = await resG.json();
                    grupoSelect.innerHTML = type === 'xml_encuestas' ? '<option value="">-- Exportar toda la Acción Formativa --</option>' : '<option value="">-- Selecciona Grupo --</option>';
                    grupos.forEach(g => grupoSelect.innerHTML += `<option value="${g.id}">Grupo ${g.numero_grupo}</option>`);
                    grupoSelect.disabled = false;
                    loadedData[type].grupos = grupos;
                    if (PRELOAD.grupo_id) {
                        grupoSelect.value = PRELOAD.grupo_id;
                        if (type === 'informe_alumno' || type === 'anexo') {
                            loadAlumnosPorGrupo(type, PRELOAD.grupo_id);
                        }
                    }
                }
            }
        }
    } else if (convSelect && convSelect.value && planSelect && planSelect.options.length <= 1) {
        // Fallback to normal behavior if just Convocatoria was pre-selected on main page
        loadPlanes(type, convSelect.value);
    }
}

function closeModal() {
    document.querySelectorAll('.modal').forEach(m => m.classList.remove('active'));
}

window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        closeModal();
    }
}

function loadPlanes(type, convocatoriaId) {
    let suffix = type === 'recibi' ? '' : (type === 'didactica' ? 'Didactica' : (type === 'informe' ? 'Informe' : (type === 'tutorias' ? 'Tutorias' : (type === 'acta' ? 'Acta' : (type === 'informe_alumno' ? 'InformeAlumno' : (type === 'diploma' ? 'Diploma' : (type === 'xml' ? 'Xml' : (type === 'xml_encuestas' ? 'XmlEnc' : 'Anexo'))))))));
    const planSelect = document.getElementById('planSelect' + suffix);
    const accionSelect = document.getElementById('accionSelect' + suffix);
    const alumnoSelect = document.getElementById('alumnoSelect' + suffix); // null if didactica/informe/tutorias/acta/diploma
    const grupoSelect = document.getElementById('grupoSelect' + suffix); // null if not didactica/informe/tutorias/acta/diploma/informe_alumno
    
    // Reset options
    planSelect.innerHTML = '<option value="">-- Selecciona Plan --</option>';
    planSelect.disabled = true;
    
    accionSelect.innerHTML = '<option value="">-- Primero elige Plan --</option>';
    accionSelect.disabled = true;
    
    if (alumnoSelect) {
        alumnoSelect.innerHTML = '<option value="">-- Primero elige Acción Formativa --</option>';
        alumnoSelect.disabled = true;
        loadedData[type].alumnos = [];
    }
    if (grupoSelect) {
        grupoSelect.innerHTML = '<option value="">-- Primero elige Acción Formativa --</option>';
        grupoSelect.disabled = true;
        loadedData[type].grupos = [];
    }
    
    loadedData[type].context = null;
    
    if (!convocatoriaId) return;
    
    fetch(`api_documentos_cascade.php?action=get_planes&convocatoria_id=${convocatoriaId}`)
        .then(res => res.json())
        .then(data => {
            if (data.length > 0) {
                data.forEach(p => {
                    let opt = document.createElement('option');
                    opt.value = p.id;
                    opt.textContent = `${p.codigo ? p.codigo + ' - ' : ''}${p.nombre}`;
                    planSelect.appendChild(opt);
                });
                planSelect.disabled = false;
            } else {
                planSelect.innerHTML = '<option value="">-- No hay planes registrados --</option>';
            }
        })
        .catch(err => {
            console.error('Error fetching planes:', err);
            alert('Error al cargar planes.');
        });
}

function loadAcciones(type, planId) {
    let suffix = type === 'recibi' ? '' : (type === 'didactica' ? 'Didactica' : (type === 'informe' ? 'Informe' : (type === 'tutorias' ? 'Tutorias' : (type === 'acta' ? 'Acta' : (type === 'informe_alumno' ? 'InformeAlumno' : (type === 'diploma' ? 'Diploma' : (type === 'xml' ? 'Xml' : (type === 'xml_encuestas' ? 'XmlEnc' : 'Anexo'))))))));
    const accionSelect = document.getElementById('accionSelect' + suffix);
    const alumnoSelect = document.getElementById('alumnoSelect' + suffix);
    const grupoSelect = document.getElementById('grupoSelect' + suffix);
    
    accionSelect.innerHTML = '<option value="">-- Selecciona Acción Formativa --</option>';
    accionSelect.disabled = true;
    
    if (alumnoSelect) {
        alumnoSelect.innerHTML = '<option value="">-- Primero elige Acción Formativa --</option>';
        alumnoSelect.disabled = true;
        loadedData[type].alumnos = [];
    }
    if (grupoSelect) {
        grupoSelect.innerHTML = '<option value="">-- Primero elige Acción Formativa --</option>';
        grupoSelect.disabled = true;
        loadedData[type].grupos = [];
    }
    
    loadedData[type].context = null;
    
    if (!planId) return;
    
    fetch(`api_documentos_cascade.php?action=get_acciones&plan_id=${planId}`)
        .then(res => res.json())
        .then(data => {
            if (data.length > 0) {
                data.forEach(af => {
                    let opt = document.createElement('option');
                    opt.value = af.id;
                    opt.textContent = `${af.num_accion ? '#' + af.num_accion + ' - ' : ''}${af.titulo}`;
                    accionSelect.appendChild(opt);
                });
                accionSelect.disabled = false;
            } else {
                accionSelect.innerHTML = '<option value="">-- No hay acciones registradas --</option>';
            }
        })
        .catch(err => {
            console.error('Error fetching acciones:', err);
            alert('Error al cargar acciones formativas.');
        });
}

function loadAlumnos(type, accionId) {
    const alumnoSelect = document.getElementById(type === 'recibi' ? 'alumnoSelect' : 'alumnoSelectAnexo');
    
    alumnoSelect.innerHTML = '<option value="">-- Buscando alumnos... --</option>';
    alumnoSelect.disabled = true;
    
    loadedData[type].alumnos = [];
    loadedData[type].context = null;
    
    if (!accionId) {
        alumnoSelect.innerHTML = '<option value="">-- Primero elige Acción Formativa --</option>';
        return;
    }
    
    fetch(`api_documentos_cascade.php?action=get_alumnos&accion_id=${accionId}`)
        .then(res => res.json())
        .then(data => {
            loadedData[type].alumnos = data.alumnos || [];
            loadedData[type].context = data.context || null;
            
            alumnoSelect.innerHTML = '';
            
            // Default option
            let defOpt = document.createElement('option');
            defOpt.value = '';
            defOpt.textContent = type === 'recibi' ? '-- Todos los alumnos (Generación Masiva) --' : '-- Todos los alumnos matriculados --';
            alumnoSelect.appendChild(defOpt);
            
            if (loadedData[type].alumnos.length > 0) {
                loadedData[type].alumnos.forEach(a => {
                    let opt = document.createElement('option');
                    opt.value = a.id;
                    
                    let nom = `${a.primer_apellido || ''} ${a.segundo_apellido || ''}`.trim() + `, ${a.nombre}`;
                    opt.textContent = `${nom} (${a.dni})`;
                    
                    // Set extra details
                    opt.setAttribute('data-nombre', `${a.nombre} ${a.primer_apellido || ''} ${a.segundo_apellido || ''}`.trim());
                    opt.setAttribute('data-dni', a.dni);
                    
                    alumnoSelect.appendChild(opt);
                });
                alumnoSelect.disabled = false;
            } else {
                alumnoSelect.innerHTML = '<option value="">-- No hay alumnos matriculados --</option>';
            }
        })
        .catch(err => {
            console.error('Error fetching alumnos:', err);
            alert('Error al cargar alumnos.');
        });
}

function loadGrupos(type, accionId) {
    let suffix = type === 'recibi' ? '' : (type === 'didactica' ? 'Didactica' : (type === 'informe' ? 'Informe' : (type === 'tutorias' ? 'Tutorias' : (type === 'acta' ? 'Acta' : (type === 'informe_alumno' ? 'InformeAlumno' : (type === 'diploma' ? 'Diploma' : (type === 'xml' ? 'Xml' : (type === 'xml_encuestas' ? 'XmlEnc' : 'Anexo'))))))));
    const grupoSelect = document.getElementById('grupoSelect' + suffix);
    
    grupoSelect.innerHTML = type === 'xml_encuestas' ? '<option value="">-- Exportar toda la Acción Formativa --</option>' : '<option value="">-- Selecciona Grupo --</option>';
    grupoSelect.disabled = true;
    loadedData[type].grupos = [];
    
    if (!accionId) return;
    
    // For XML surveys and Informe Alumno we only need groups (wait, informe_alumno needs both, but 'recibi' doesn't do loadAlumnos until loadGrupos is missing. Actually wait, we should fetch alumnos for informe_alumno!)
    // Wait, loadGrupos in documentacion.php is called when accion_id changes. Then we must define loadAlumnos!
    
    fetch(`api_documentos_cascade.php?action=get_grupos&accion_id=${accionId}`)
        .then(res => res.json())
        .then(data => {
            if (data.length > 0) {
                loadedData[type].grupos = data;
                data.forEach(g => {
                    let opt = document.createElement('option');
                    opt.value = g.id;
                    opt.textContent = `Grupo ${g.numero_grupo}`;
                    grupoSelect.appendChild(opt);
                });
                grupoSelect.disabled = false;
            } else {
                grupoSelect.innerHTML = '<option value="">-- No hay grupos registrados --</option>';
            }
        })
        .catch(err => {
            console.error('Error fetching grupos:', err);
            alert('Error al cargar grupos.');
        });
}

function goToDidactica() {
    let grupoSelect = document.getElementById('grupoSelectDidactica');
    let grupoId = grupoSelect.value;
    if (!grupoId) {
        alert("Por favor, selecciona un grupo.");
        return;
    }
    window.location.href = `documentacion_didactica.php?grupo_id=${grupoId}`;
}

function generateInformeGrupoPDF() {
    let grupoSelect = document.getElementById('grupoSelectInforme');
    let grupoId = grupoSelect.value;
    if (!grupoId) {
        alert("Por favor, selecciona un grupo.");
        return;
    }
    window.location.href = `pdf_informe_evaluaciones.php?grupo_id=${grupoId}`;
    closeModal();
}

function generateRecibiPDF() {
    let selectAccion = document.getElementById('accionSelect');
    let selectAlumno = document.getElementById('alumnoSelect');
    
    let accionId = selectAccion.value;
    let alumnoId = selectAlumno.value;
    
    if (!accionId) {
        alert("Por favor, selecciona una acción formativa válida.");
        return;
    }
    
    window.location.href = `pdf_recibi_material.php?accion_id=${accionId}&alumno_id=${alumnoId}`;
    closeModal();
}

function generateAnexo1PDF() {
    let select = document.getElementById('alumnoSelectAnexo');
    let selectAccion = document.getElementById('accionSelectAnexo');
    let alumnoId = select.value;
    let accionId = selectAccion.value;
    
    if (!accionId) {
        alert("Por favor, selecciona al menos una acción formativa.");
        return;
    }
    
    // Mostramos un indicador de carga porque puede tardar si son muchos alumnos
    const btn = document.querySelector("#anexoModal .btn-primary");
    const originalText = btn.innerText;
    btn.innerText = "Generando PDF, por favor espera...";
    btn.disabled = true;

    fetch(`api_anexo1_html.php?accion_id=${accionId}&alumno_id=${alumnoId}`)
    .then(response => {
        if (!response.ok) throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        return response.text();
    })
    .then(htmlStr => {
        if (htmlStr.includes("SQL ERROR:")) {
            throw new Error(htmlStr.replace(/(<([^>]+)>)/gi, ""));
        }
        let fname = alumnoId ? `Anexo1_Alumno_${alumnoId}.pdf` : `Anexo1_Todos.pdf`;
        
        // Creamos un contenedor oculto para que html2canvas pueda renderizar
        const container = document.createElement('div');
        container.style.position = 'absolute';
        container.style.top = '0';
        container.style.left = '0';
        container.style.width = '800px';
        container.style.zIndex = '-9999';
        container.innerHTML = htmlStr;
        
        // Temporarily remove overflow-x: hidden from body to prevent clipping
        const origOverflowX = document.body.style.overflowX;
        document.body.style.overflowX = 'visible';
        
        document.body.appendChild(container);
        
        const students = container.querySelectorAll('.student-wrapper');
        
        if (students.length > 0) {
            // Configuración base de html2pdf
            const opt = {
                margin:       10, // mm
                filename:     fname,
                image:        { type: 'jpeg', quality: 0.98 },
                html2canvas:  { scale: 2, useCORS: true, logging: false, windowWidth: 800 },
                jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' },
                pagebreak:    { mode: 'css' }
            };
            
            // Iniciamos la generación con el primer estudiante
            let worker = html2pdf().set(opt).from(students[0]).toPdf();
            
            // Añadimos el resto de estudiantes encadenando páginas
            for (let i = 1; i < students.length; i++) {
                worker = worker.get('pdf').then(pdf => {
                    pdf.addPage();
                }).from(students[i]).toContainer().toCanvas().toPdf();
            }
            
            // Finalmente guardamos el documento
            worker.save().then(() => {
                document.body.removeChild(container);
                document.body.style.overflowX = origOverflowX;
                btn.innerText = originalText;
                btn.disabled = false;
                closeModal();
            }).catch(e => {
                console.error(e);
                alert("Error al generar PDF: " + e.message);
                document.body.removeChild(container);
                document.body.style.overflowX = origOverflowX;
                btn.innerText = originalText;
                btn.disabled = false;
            });
        } else {
            alert("No hay alumnos para generar.");
            document.body.removeChild(container);
            btn.innerText = originalText;
            btn.disabled = false;
        }
    })
    .catch(error => {
        console.error('Error fetching Anexo 1 HTML:', error);
        alert("Error: " + error.message);
        btn.innerText = originalText;
        btn.disabled = false;
    });
}

// Generate Tutorías Excel Export
function generateTutoriasExcel() {
    let accionId = document.getElementById('accionSelectTutorias').value;
    let grupoId = document.getElementById('grupoSelectTutorias').value;

    if (!accionId || !grupoId) {
        alert("Por favor selecciona Acción Formativa y Grupo.");
        return;
    }

    // Redirect to the excel generation endpoint
    window.location.href = `excel_informe_tutorias.php?accion_id=${accionId}&grupo_id=${grupoId}`;
    closeModal();
}
// Generate Acta de Evaluación Final
function generateActaEvaluacionPDF() {
    let accionId = document.getElementById('accionSelectActa').value;
    let grupoId = document.getElementById('grupoSelectActa').value;

    if (!accionId || !grupoId) {
        alert("Por favor selecciona Acción Formativa y Grupo.");
        return;
    }

    // Redirect to the acta generation endpoint
    window.open(`pdf_acta_evaluacion.php?accion_id=${accionId}&grupo_id=${grupoId}`, '_blank');
    closeModal();
}

function loadAlumnosPorGrupo(type, grupoId) {
    let suffix = type === 'recibi' ? '' : (type === 'informe_alumno' ? 'InformeAlumno' : (type === 'anexo' ? 'Anexo' : ''));
    const alumnoSelect = document.getElementById('alumnoSelect' + suffix);
    if (!alumnoSelect) return;
    
    alumnoSelect.innerHTML = '<option value="">-- Selecciona Alumno --</option>';
    alumnoSelect.disabled = true;

    if (!grupoId) return;

    fetch(`api_get_alumnos_by_grupo.php?grupo_id=${grupoId}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                data.alumnos.forEach(a => {
                    alumnoSelect.innerHTML += `<option value="${a.id}">${a.apellidos}, ${a.nombre}</option>`;
                });
                alumnoSelect.disabled = false;
            } else {
                alert('Error al cargar alumnos: ' + (data.message || 'Error desconocido'));
            }
        })
        .catch(err => {
            console.error(err);
            alert('Error de conexión al cargar alumnos.');
        });
}

// Generate Informe Seguimiento Alumno
function generateInformeAlumnoPDF() {
    let accionId = document.getElementById('accionSelectInformeAlumno').value;
    let grupoId = document.getElementById('grupoSelectInformeAlumno').value;
    let alumnoId = document.getElementById('alumnoSelectInformeAlumno').value;

    if (!accionId || !grupoId || !alumnoId) {
        alert("Por favor selecciona Acción Formativa, Grupo y Alumno.");
        return;
    }

    window.open(`pdf_informe_alumno.php?accion_id=${accionId}&grupo_id=${grupoId}&alumno_id=${alumnoId}`, '_blank');
    closeModal();
}

function generateDiplomaList() {
    const accionId = document.getElementById('accionSelectDiploma').value;
    const grupoId = document.getElementById('grupoSelectDiploma').value;
    
    if (!accionId || !grupoId) {
        alert('Por favor, selecciona una acción y un grupo.');
        return;
    }

    window.location.href = `diplomas.php?accion_id=${accionId}&grupo_id=${grupoId}`;
}

    function generateXML() {
        const accionId = document.getElementById('accionSelectXml').value;
        const grupoId = document.getElementById('grupoSelectXml').value;
        
        if (!accionId || !grupoId) {
            alert('Por favor, selecciona una acción y un grupo.');
            return;
        }

        window.location.href = `export_xml_grupo.php?accion_id=${accionId}&grupo_id=${grupoId}`;
    }

    function generateXMLEncuestas() {
        const accionId = document.getElementById('accionSelectXmlEnc').value;
        const grupoId = document.getElementById('grupoSelectXmlEnc').value;
        
        if (!accionId) {
            alert('Por favor, selecciona al menos una acción formativa.');
            return;
        }

        let url = `exportar_encuestas_xml.php?accion_id=${accionId}`;
        if (grupoId) {
            url += `&grupo_id=${grupoId}`;
        }
        
        window.location.href = url;
    }
