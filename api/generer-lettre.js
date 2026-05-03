/**
 * api/generer-lettre.js — Génération de lettre de stage en .docx
 * Usage: node generer-lettre.js --prenom=X --nom=X --ref=X --debut=X --fin=X --mois=X --ref_cand=X --direction=X
 */
const { Document, Packer, Paragraph, TextRun, AlignmentType, HeadingLevel, BorderStyle } = require('docx');
const fs = require('fs');
const path = require('path');

// Parse arguments
const args = {};
process.argv.slice(2).forEach(arg => {
    const [key, ...rest] = arg.replace(/^--/, '').split('=');
    args[key] = rest.join('=');
});

const prenom    = args.prenom    || 'Prénom';
const nom       = args.nom       || 'NOM';
const ref       = args.ref       || 'S-2026-001';
const debut     = args.debut     || '';
const fin       = args.fin       || '';
const mois      = args.mois      || '1';
const refCand   = args.ref_cand  || 'C-2026-001';
const direction = args.direction || 'Direction concernée';
const outFile   = args.out       || path.join(__dirname, 'lettre_' + ref + '.docx');

function formatDate(dateStr) {
    if (!dateStr) return '';
    const d = new Date(dateStr);
    return d.toLocaleDateString('fr-FR', { day: '2-digit', month: 'long', year: 'numeric' });
}

const doc = new Document({
    styles: {
        default: {
            document: {
                run: { font: 'Times New Roman', size: 24 }
            }
        }
    },
    sections: [{
        properties: {
            page: { margin: { top: 1440, right: 1440, bottom: 1440, left: 1800 } }
        },
        children: [
            // Entête organisation
            new Paragraph({
                children: [
                    new TextRun({ text: 'ORGANISATION', bold: true, size: 28, font: 'Times New Roman' }),
                ],
                alignment: AlignmentType.CENTER,
                spacing: { after: 100 },
            }),
            new Paragraph({
                children: [new TextRun({ text: 'Département des Ressources Humaines', size: 22, font: 'Times New Roman' })],
                alignment: AlignmentType.CENTER,
                spacing: { after: 400 },
            }),

            // Titre
            new Paragraph({
                children: [new TextRun({ text: 'LETTRE DE STAGE', bold: true, size: 32, font: 'Times New Roman', underline: {} })],
                alignment: AlignmentType.CENTER,
                spacing: { before: 200, after: 200 },
            }),
            new Paragraph({
                children: [new TextRun({ text: `Référence : ${ref}`, size: 22, font: 'Times New Roman', italics: true })],
                alignment: AlignmentType.CENTER,
                spacing: { after: 400 },
            }),

            // Corps
            new Paragraph({
                children: [new TextRun({ text: `Nous, soussignés, certifions par la présente que :`, size: 24, font: 'Times New Roman' })],
                spacing: { after: 200 },
            }),

            new Paragraph({
                children: [
                    new TextRun({ text: `M./Mme ${prenom} ${nom.toUpperCase()}`, bold: true, size: 26, font: 'Times New Roman' }),
                ],
                alignment: AlignmentType.CENTER,
                spacing: { before: 200, after: 200 },
            }),

            new Paragraph({
                children: [new TextRun({
                    text: `effectue un stage au sein de notre organisation, à la ${direction}, dans le cadre de la candidature référencée ${refCand}.`,
                    size: 24, font: 'Times New Roman'
                })],
                spacing: { after: 200 },
            }),

            new Paragraph({
                children: [
                    new TextRun({ text: 'Période du stage : ', bold: true, size: 24, font: 'Times New Roman' }),
                    new TextRun({ text: `du ${formatDate(debut)} au ${formatDate(fin)}`, size: 24, font: 'Times New Roman' }),
                    new TextRun({ text: ` (soit ${mois} mois)`, size: 24, font: 'Times New Roman', italics: true }),
                ],
                spacing: { after: 200 },
            }),

            new Paragraph({
                children: [new TextRun({
                    text: 'Ce stage est effectué dans les conditions suivantes :',
                    size: 24, font: 'Times New Roman'
                })],
                spacing: { after: 100 },
            }),
            new Paragraph({
                children: [new TextRun({ text: '• Stage conventionné à titre de formation professionnelle', size: 24, font: 'Times New Roman' })],
                spacing: { after: 100 },
            }),
            new Paragraph({
                children: [new TextRun({ text: '• Soumis aux règlements intérieurs de l\'organisation', size: 24, font: 'Times New Roman' })],
                spacing: { after: 300 },
            }),

            new Paragraph({
                children: [new TextRun({
                    text: 'La présente lettre est délivrée pour servir et valoir ce que de droit.',
                    size: 24, font: 'Times New Roman', italics: true
                })],
                spacing: { after: 400 },
            }),

            // Date et signature
            new Paragraph({
                children: [new TextRun({ text: `Fait le ${new Date().toLocaleDateString('fr-FR')}`, size: 24, font: 'Times New Roman' })],
                alignment: AlignmentType.RIGHT,
                spacing: { after: 200 },
            }),

            new Paragraph({
                children: [new TextRun({ text: 'Le Responsable RH', bold: true, size: 24, font: 'Times New Roman' })],
                alignment: AlignmentType.RIGHT,
                spacing: { after: 100 },
            }),
            new Paragraph({
                children: [new TextRun({ text: '_______________________', size: 24, font: 'Times New Roman' })],
                alignment: AlignmentType.RIGHT,
            }),
        ]
    }]
});

Packer.toBuffer(doc).then(buffer => {
    fs.writeFileSync(outFile, buffer);
    console.log('OK:' + outFile);
}).catch(err => {
    console.error('ERROR:' + err.message);
    process.exit(1);
});
