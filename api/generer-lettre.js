#!/usr/bin/env node
/**
 * StagIA - Générateur de lettre de stage DOCX
 * Usage: node generer-lettre.js '<json_data>'
 */

const { Document, Paragraph, TextRun, HeadingLevel, AlignmentType, Packer, BorderStyle } = require('docx');
const fs = require('fs');
const os = require('os');
const path = require('path');

let data;
try {
    data = JSON.parse(process.argv[2] || '{}');
} catch (e) {
    console.log(JSON.stringify({ error: 'JSON invalide: ' + e.message }));
    process.exit(1);
}

const dateToFr = (dateStr) => {
    if (!dateStr) return '';
    const d = new Date(dateStr);
    return d.toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' });
};

const mois = (d1, d2) => {
    const start = new Date(d1);
    const end = new Date(d2);
    const diff = (end - start) / (1000 * 60 * 60 * 24 * 30.5);
    return Math.round(diff * 10) / 10;
};

const doc = new Document({
    sections: [{
        properties: {
            page: {
                margin: { top: 1440, right: 1440, bottom: 1440, left: 1440 }
            }
        },
        children: [
            new Paragraph({
                alignment: AlignmentType.CENTER,
                spacing: { after: 400 },
                children: [
                    new TextRun({
                        text: 'LETTRE DE STAGE',
                        bold: true,
                        size: 32,
                        color: '1b2a6b',
                    })
                ]
            }),
            new Paragraph({
                alignment: AlignmentType.RIGHT,
                spacing: { after: 600 },
                children: [
                    new TextRun({
                        text: `Conakry, le ${data.date_lettre || new Date().toLocaleDateString('fr-FR')}`,
                        size: 22,
                    })
                ]
            }),
            new Paragraph({
                spacing: { after: 200 },
                children: [
                    new TextRun({ text: 'Référence stage : ', bold: true, size: 22 }),
                    new TextRun({ text: data.ref_stage || '', size: 22 }),
                ]
            }),
            new Paragraph({
                spacing: { after: 600 },
                children: [
                    new TextRun({ text: 'Référence candidature : ', bold: true, size: 22 }),
                    new TextRun({ text: data.ref_cand || '', size: 22 }),
                ]
            }),
            new Paragraph({
                spacing: { after: 200 },
                children: [
                    new TextRun({ text: 'Objet : Lettre de stage', bold: true, size: 22 }),
                ]
            }),
            new Paragraph({
                spacing: { after: 400 },
                children: [
                    new TextRun({ text: `${data.civilite || 'M.'} ${data.prenom} ${data.nom}`, bold: true, size: 22, underline: {} }),
                ]
            }),
            new Paragraph({
                spacing: { after: 400 },
                children: [
                    new TextRun({
                        text: `Nous avons le plaisir de vous informer que votre stage au sein de notre organisation a été approuvé.`,
                        size: 22,
                    })
                ]
            }),
            new Paragraph({
                spacing: { after: 300 },
                children: [
                    new TextRun({ text: 'Informations du stage :', bold: true, size: 22, underline: {} }),
                ]
            }),
            new Paragraph({
                spacing: { after: 200 },
                children: [
                    new TextRun({ text: 'Direction d\'accueil : ', bold: true, size: 22 }),
                    new TextRun({ text: data.direction || '', size: 22 }),
                ]
            }),
            new Paragraph({
                spacing: { after: 200 },
                children: [
                    new TextRun({ text: 'Domaine : ', bold: true, size: 22 }),
                    new TextRun({ text: data.domaine || '', size: 22 }),
                ]
            }),
            new Paragraph({
                spacing: { after: 200 },
                children: [
                    new TextRun({ text: 'Date de début : ', bold: true, size: 22 }),
                    new TextRun({ text: dateToFr(data.date_debut), size: 22 }),
                ]
            }),
            new Paragraph({
                spacing: { after: 200 },
                children: [
                    new TextRun({ text: 'Date de fin : ', bold: true, size: 22 }),
                    new TextRun({ text: dateToFr(data.date_fin), size: 22 }),
                ]
            }),
            new Paragraph({
                spacing: { after: 200 },
                children: [
                    new TextRun({ text: 'Durée : ', bold: true, size: 22 }),
                    new TextRun({ text: `${mois(data.date_debut, data.date_fin)} mois`, size: 22 }),
                ]
            }),
            ...(data.enc_nom ? [new Paragraph({
                spacing: { after: 400 },
                children: [
                    new TextRun({ text: 'Encadrant(e) : ', bold: true, size: 22 }),
                    new TextRun({ text: `${data.enc_prenom} ${data.enc_nom}`, size: 22 }),
                ]
            })] : []),
            new Paragraph({
                spacing: { before: 400, after: 200 },
                children: [
                    new TextRun({
                        text: 'Nous vous souhaitons la bienvenue et espérons que cette expérience sera enrichissante pour votre formation professionnelle.',
                        size: 22,
                    })
                ]
            }),
            new Paragraph({
                spacing: { before: 600, after: 200 },
                children: [
                    new TextRun({ text: 'La Direction des Ressources Humaines', bold: true, size: 22 }),
                ]
            }),
        ]
    }]
});

const tmpFile = path.join(os.tmpdir(), `lettre-${data.ref_stage}-${Date.now()}.docx`);

Packer.toBuffer(doc).then((buffer) => {
    fs.writeFileSync(tmpFile, buffer);
    console.log(JSON.stringify({ file: tmpFile }));
}).catch((err) => {
    console.log(JSON.stringify({ error: err.message }));
});
