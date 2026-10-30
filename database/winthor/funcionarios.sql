-- Funcionários ativos do WinThor no formato da planilha de carga (aba USUARIOS).
-- Rodar no WinThor, exportar o resultado para Excel/CSV e importar com:
--   php artisan usuarios:importar funcionarios.xlsx --senha-padrao='Bin@2026' --dry-run   (confere)
--   php artisan usuarios:importar funcionarios.xlsx --senha-padrao='Bin@2026'             (grava)
--
-- Antes de importar, conferir no Excel:
--   - LOGIN: montado como primeironome.ultimonome; nomes repetidos dão login repetido e a importação avisa.
--   - LIDER_ESTOQUE, RESP_BAIXA e ADMIN: marcar SIM em quem tem esse papel.
--   - APROVA_SETORES: nos líderes do estoque, deixar "SHOWROOM; NINJA PLACE" (o nome tem que ser igual ao da coluna SETOR).
--
-- Tabelas do WinThor usadas: PCEMPR (funcionários) e PCSETOR (setores). Se a versão de vocês tiver outro
-- nome de coluna, ajuste aqui; o resto da planilha não depende do WinThor.
SELECT
    E.MATRICULA AS MATRICULA,
    INITCAP(TRIM(E.NOME)) AS NOME,
    REGEXP_REPLACE(
        LOWER(TRANSLATE(
            REGEXP_SUBSTR(TRIM(E.NOME), '^[^ ]+') || '.' || REGEXP_SUBSTR(TRIM(E.NOME), '[^ ]+$'),
            'ÁÀÂÃÄÉÈÊËÍÌÎÏÓÒÔÕÖÚÙÛÜÇáàâãäéèêëíìîïóòôõöúùûüç',
            'AAAAAEEEEIIIIOOOOOUUUUCaaaaaeeeeiiiiooooouuuuc'
        )),
        '[^a-z0-9.]', ''
    ) AS LOGIN,
    '' AS CARGO,
    UPPER(TRIM(S.DESCRICAO)) AS SETOR,
    CASE
        WHEN UPPER(E.NOME) LIKE 'OSMAR%' AND UPPER(S.DESCRICAO) LIKE '%SHOWROOM%' THEN 'LIDER'
        WHEN UPPER(E.NOME) LIKE 'GUSTAVO%' AND UPPER(S.DESCRICAO) LIKE '%NINJA%' THEN 'LIDER'
        ELSE ''
    END AS PAPEL_SETOR,
    CASE WHEN UPPER(TRIM(S.DESCRICAO)) = 'ESTOQUE' THEN 'SIM' ELSE 'NAO' END AS ESTOQUE,
    'NAO' AS LIDER_ESTOQUE,
    'NAO' AS RESP_BAIXA,
    'NAO' AS ADMIN,
    '' AS APROVA_SETORES,
    'SIM' AS INCLUIR
FROM PCEMPR E
JOIN PCSETOR S ON S.CODSETOR = E.CODSETOR
WHERE E.SITUACAO = 'A'
ORDER BY S.DESCRICAO, E.NOME;
