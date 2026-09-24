import './bootstrap';

// As fontes ficam aqui e não no app.css: importadas pelo Tailwind, os caminhos dos arquivos quebram.
import '@fontsource-variable/geist/index.css';
import '@fontsource-variable/geist-mono/index.css';

import Alpine from 'alpinejs';

let proximaChave = 1;

const itemVazio = () => ({ chave: proximaChave++, descricao: '', quantidade: '', unidade: 'UN' });

/**
 * Formulário de nova requisição: itens dinâmicos e assinatura por senha antes do envio.
 * As regras de verdade estão no backend; aqui é só conforto de preenchimento.
 */
Alpine.data('novaRequisicao', ({ tipo, itens, erros, abrirAssinatura }) => ({
    tipo,
    itens: itens.length > 0 ? itens.map((item) => ({ ...itemVazio(), ...item })) : [itemVazio()],
    erros,
    assinando: abrirAssinatura,
    enviando: false,

    init() {
        if (this.assinando) {
            this.$nextTick(() => this.$refs.senha.focus());
        }
    },

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

    abrirAssinatura() {
        if (!this.$root.reportValidity()) {
            return;
        }

        this.assinando = true;
        this.$nextTick(() => this.$refs.senha.focus());
    },

    aoEnviar(evento) {
        if (!this.assinando) {
            evento.preventDefault();
            this.abrirAssinatura();

            return;
        }

        this.enviando = true;
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
