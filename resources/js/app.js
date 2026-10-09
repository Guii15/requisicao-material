import './bootstrap';

// As fontes ficam aqui e não no app.css: importadas pelo Tailwind, os caminhos dos arquivos quebram.
import '@fontsource-variable/dm-sans/index.css';
import '@fontsource-variable/manrope/index.css';

import Alpine from 'alpinejs';

let proximaChave = 1;

const itemVazio = () => ({ chave: proximaChave++, codigo: '', descricao: '', quantidade: '', unidade: 'UN' });

/**
 * Formulário de nova requisição: itens dinâmicos. As regras de verdade estão no backend;
 * aqui é só conforto de preenchimento.
 */
Alpine.data('novaRequisicao', ({ tipo, itens, erros }) => ({
    tipo,
    itens: itens.length > 0 ? itens.map((item) => ({ ...itemVazio(), ...item })) : [itemVazio()],
    erros,
    enviando: false,

    adicionar() {
        this.itens.push(itemVazio());
        this.$nextTick(() => {
            const campos = this.$root.querySelectorAll('[data-descricao]');
            campos[campos.length - 1]?.focus();
        });
    },

    remover(indice) {
        if (this.itens.length > 1) {
            this.itens.splice(indice, 1);
            this.erros = {};
        }
    },

    erroDoItem(indice, campo) {
        return this.erros[`itens.${indice}.${campo}`] ?? null;
    },
}));

/**
 * Número do painel contando de 0 até o valor real ao carregar a página. Puramente
 * decorativo (o valor final é o mesmo que o backend mandou) — pula direto pro fim
 * se a pessoa pediu menos movimento na tela.
 */
Alpine.data('contador', ({ ate }) => ({
    valor: 0,

    init() {
        const reduzido = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        if (reduzido || ate <= 0) {
            this.valor = ate;

            return;
        }

        const duracao = 700;
        const inicio = performance.now();

        const passo = (agora) => {
            const progresso = Math.min((agora - inicio) / duracao, 1);
            this.valor = Math.round(ate * (1 - (1 - progresso) ** 3));

            if (progresso < 1) {
                requestAnimationFrame(passo);
            }
        };

        requestAnimationFrame(passo);
    },
}));

/**
 * Assinatura por desenho (retirada): quem retira desenha o dedo/mouse na tela, sem precisar
 * de login. O canvas tem resolução fixa (não depende do tamanho do modal no momento em que
 * o Alpine inicializa, que pode estar escondido ainda) — a posição do traço é reescalada
 * pra bater com o tamanho exibido, seja qual for.
 */
Alpine.data('assinaturaDesenho', () => ({
    enviando: false,
    desenhando: false,
    vazio: true,
    dataUrl: '',
    ctx: null,

    init() {
        const tela = this.$refs.tela;
        tela.width = 600;
        tela.height = 200;
        this.ctx = tela.getContext('2d');
        this.ctx.lineWidth = 2.5;
        this.ctx.lineCap = 'round';
        this.ctx.lineJoin = 'round';
        this.ctx.strokeStyle = '#0a0a0a';
    },

    posicao(evento) {
        const tela = this.$refs.tela;
        const retangulo = tela.getBoundingClientRect();
        const ponto = evento.touches ? evento.touches[0] : evento;

        return {
            x: (ponto.clientX - retangulo.left) * (tela.width / retangulo.width),
            y: (ponto.clientY - retangulo.top) * (tela.height / retangulo.height),
        };
    },

    comecar(evento) {
        evento.preventDefault();
        this.desenhando = true;
        const { x, y } = this.posicao(evento);
        this.ctx.beginPath();
        this.ctx.moveTo(x, y);
    },

    desenhar(evento) {
        if (!this.desenhando) {
            return;
        }

        evento.preventDefault();
        const { x, y } = this.posicao(evento);
        this.ctx.lineTo(x, y);
        this.ctx.stroke();
        this.vazio = false;
    },

    parar() {
        this.desenhando = false;
    },

    limpar() {
        this.ctx.clearRect(0, 0, this.$refs.tela.width, this.$refs.tela.height);
        this.vazio = true;
        this.dataUrl = '';
    },

    aoEnviar() {
        this.dataUrl = this.$refs.tela.toDataURL('image/png');
        this.enviando = true;
    },
}));

/**
 * Lista de requisições: busca, aba e exportação em CSV sobre as linhas que a página já trouxe.
 * Cada linha da lista chama aparece(indice) para saber se continua visível.
 */
Alpine.data('listaRequisicoes', (linhas) => ({
    linhas,
    busca: '',
    aba: 'todas',

    aparece(indice) {
        const linha = this.linhas[indice];
        const termo = this.busca.trim().toLowerCase();

        if (termo && !linha.texto.includes(termo)) {
            return false;
        }

        if (this.aba === 'pendentes') {
            return ['aguardando', 'andamento', 'alerta'].includes(linha.grupo);
        }

        if (this.aba === 'concluidas') {
            return linha.grupo === 'concluida';
        }

        return true;
    },

    get visiveis() {
        return this.linhas.filter((_, indice) => this.aparece(indice)).length;
    },

    exportar() {
        const cabecalho = ['Número', 'Tipo', 'Situação', 'Produto', 'Itens', 'Solicitante', 'Setor', 'Abertura'];
        const celula = (valor) => '"' + String(valor ?? '').replaceAll('"', '""') + '"';
        const corpo = this.linhas.filter((_, indice) => this.aparece(indice)).map((linha) => linha.csv.map(celula).join(';'));
        const csv = [cabecalho.map(celula).join(';'), ...corpo].join('\r\n');
        const endereco = URL.createObjectURL(new Blob(['\ufeff', csv], { type: 'text/csv;charset=utf-8' }));
        const link = document.createElement('a');

        link.href = endereco;
        link.download = 'requisicoes.csv';
        link.click();
        URL.revokeObjectURL(endereco);
    },
}));

/**
 * Linhas de tabela com data-href abrem a requisição com um clique em qualquer parte.
 * Ctrl/Cmd abre em outra aba; texto selecionado com o mouse não conta como clique.
 */
document.addEventListener('click', (evento) => {
    const linha = evento.target.closest('tr[data-href]');

    if (!linha || evento.target.closest('a, button, input, label') || window.getSelection()?.toString()) {
        return;
    }

    if (evento.ctrlKey || evento.metaKey) {
        window.open(linha.dataset.href, '_blank');
    } else {
        window.location.href = linha.dataset.href;
    }
});

window.Alpine = Alpine;
Alpine.start();
