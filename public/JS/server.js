// server.js
const express = require('express');
const { spawn } = require('child_process');
const path = require('path');
const https = require('https');
const fs = require('fs');
const cors = require('cors');

const app = express();
const PORT = 3000; // Puedes elegir otro puerto si lo prefieres

const privateKey = fs.readFileSync('/etc/apache2/ssl/server.key', 'utf8');
const certificate = fs.readFileSync('/etc/apache2/ssl/server.crt', 'utf8');

const credentials = { key: privateKey, cert: certificate};

// Ruta para servir archivos estáticos (HTML, CSS, JS, imágenes)
app.use(express.static(path.join(__dirname)));
app.use(cors());
// Ruta que ejecuta tu script con child_process
app.get('/preview/:id', (req, res) => {
    const id = req.params.id; // Acceder a la id desde la URL
    console.log(`ID recibida: ${id}`);

    // Usar spawn para ejecutar el script 'extraer.js'
    const scriptPath = path.join(__dirname, './extraer.js');
    const child = spawn('node', [scriptPath, id]);

    // Capturar la salida estándar (stdout)
    let output = '';
    child.stdout.on('data', (data) => {
        output += data.toString();
    });

    // Capturar errores (stderr)
    child.stderr.on('data', (data) => {
        console.error(`stderr: ${data}`);
    });

    // Cuando el proceso termine
    child.on('close', (code) => {
        if (code === 0) {
            // Enviar la salida del script como respuesta
            res.send(output);
        } else {
            console.error(`El script terminó con código de error: ${code}`);
            res.status(500).send('Error al ejecutar el script');
        }
    });
});


// Iniciar el servidor Express
https.createServer(credentials, app).listen(3000, () => {
    console.log('Servidor HTTPS corriendo en https://localhost:3000');
});
