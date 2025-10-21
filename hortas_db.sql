-- Cria o banco de dados se ele ainda não existir, garantindo que o script possa ser executado múltiplas vezes.
CREATE DATABASE IF NOT EXISTS hortas_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Seleciona o banco de dados para executar os comandos seguintes.
USE hortas_db;

-- Tabela para o catálogo de produtos/itens.
-- Armazena informações que descrevem o item, independentemente de
-- quantos dele existem no estoque. Esta é a tabela que você pré-popula.
CREATE TABLE produtos (
    id_produto INT AUTO_INCREMENT PRIMARY KEY,
    nm_produto VARCHAR(100) NOT NULL UNIQUE,
    descricao VARCHAR(255),
    unidade_medida_padrao ENUM('g','kg','ton','unidade')
);

-- Tabela para armazenar os endereços das hortas.
-- Separar o endereço permite reutilização e uma organização mais limpa.
CREATE TABLE endereco_hortas (
    id_endereco_hortas INT AUTO_INCREMENT PRIMARY KEY,
    nm_rua VARCHAR(50),
    nr_cep VARCHAR(8),
    nm_bairro VARCHAR(50),
    nm_estado CHAR(2),
    nm_cidade VARCHAR(50),
    nm_pais VARCHAR(20) DEFAULT 'Brasil'
);

-- Tabela principal das hortas.
-- Contém as informações de cada horta cadastrada no sistema.
CREATE TABLE hortas (
    id_hortas INT AUTO_INCREMENT PRIMARY KEY,
    endereco_hortas_id_endereco_hortas INT,
    nr_cnpj VARCHAR(14) UNIQUE,
    nome VARCHAR(50),
    descricao VARCHAR(255),
    visibilidade INT(1),
    receitas_geradas BIGINT,
    CONSTRAINT fk_hortas_endereco FOREIGN KEY (endereco_hortas_id_endereco_hortas)
        REFERENCES endereco_hortas(id_endereco_hortas)
        ON DELETE SET NULL ON UPDATE CASCADE
);

-- Tabela de estoques normalizada.
-- Cada linha representa um LOTE específico de um produto em uma horta.
-- Não guarda mais os detalhes do produto, apenas a quantidade e as datas do lote.
CREATE TABLE estoques (
    id_estoques INT AUTO_INCREMENT PRIMARY KEY,
    hortas_id_hortas INT,
    produto_id_produto INT,
    ds_quantidade DECIMAL(10,2),
    dt_validade DATE,
    dt_colheita DATE,
    dt_plantio DATE,
    
    CONSTRAINT fk_estoques_hortas FOREIGN KEY (hortas_id_hortas)
        REFERENCES hortas(id_hortas)
        ON DELETE CASCADE ON UPDATE CASCADE,
        
    CONSTRAINT fk_estoques_produtos FOREIGN KEY (produto_id_produto)
        REFERENCES produtos(id_produto)
        ON DELETE CASCADE ON UPDATE CASCADE
);

-- Tabela de produtores.
-- Armazena os dados dos usuários que gerenciam as hortas.
CREATE TABLE produtor (
    id_produtor INT AUTO_INCREMENT PRIMARY KEY,
    hortas_id_hortas INT,
    nome_produtor VARCHAR(50),
    telefone_produtor VARCHAR(15),
    hash_senha VARCHAR(255),
    email_produtor VARCHAR(50) UNIQUE,
    nr_cpf VARCHAR(11) UNIQUE,
    CONSTRAINT fk_produtor_hortas FOREIGN KEY (hortas_id_hortas)
        REFERENCES hortas(id_hortas)
        ON DELETE CASCADE ON UPDATE CASCADE
);

-- Tabela para registrar as ENTRADAS de itens no estoque (aumenta a quantidade).
-- Cria um histórico de tudo que foi adicionado.
CREATE TABLE entradas_estoque (
    id_entrada INT AUTO_INCREMENT PRIMARY KEY,
    estoques_id_estoques INT,
    produtor_id_produtor INT,
    dt_entrada DATETIME DEFAULT CURRENT_TIMESTAMP,
    quantidade DECIMAL(10,2) NOT NULL,
    motivo VARCHAR(255),
    CONSTRAINT fk_entradas_estoques FOREIGN KEY (estoques_id_estoques)
        REFERENCES estoques(id_estoques)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_entradas_produtor FOREIGN KEY (produtor_id_produtor)
        REFERENCES produtor(id_produtor)
        ON DELETE SET NULL ON UPDATE CASCADE
);

-- Tabela para registrar as SAÍDAS de itens do estoque (diminui a quantidade).
-- Cria um histórico de tudo que foi retirado (vendas, perdas, etc.).
CREATE TABLE saidas_estoque (
    id_saida INT AUTO_INCREMENT PRIMARY KEY,
    estoques_id_estoques INT,
    produtor_id_produtor INT,
    dt_saida DATETIME DEFAULT CURRENT_TIMESTAMP,
    quantidade DECIMAL(10,2) NOT NULL,
    motivo VARCHAR(255),
    CONSTRAINT fk_saidas_estoques FOREIGN KEY (estoques_id_estoques)
        REFERENCES estoques(id_estoques)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_saidas_produtor FOREIGN KEY (produtor_id_produtor)
        REFERENCES produtor(id_produtor)
        ON DELETE SET NULL ON UPDATE CASCADE
);


-- ===================================================================================
-- SCRIPT PARA POPULAR A TABELA DE PRODUTOS
-- Execute este bloco para ter um catálogo inicial de itens.
-- ===================================================================================

-- HORTALIÇAS
INSERT INTO produtos (nm_produto, descricao, unidade_medida_padrao) VALUES 
('Alface Crespa', 'Folhas verdes e crespas, ideal para saladas frescas.', 'unidade'),
('Alface Americana', 'Formato de cabeça compacta, muito crocante.', 'unidade'),
('Rúcula', 'Folhas de sabor intenso e levemente picante.', 'g'),
('Agrião', 'Sabor picante, rico em vitaminas, ótimo para saladas e sopas.', 'g'),
('Couve-Flor', 'Inflorescência branca e nutritiva, muito versátil na cozinha.', 'unidade'),
('Brócolis Ninja', 'Rico em nutrientes, de cor verde intensa e sabor suave.', 'unidade'),
('Cenoura', 'Raiz alaranjada, adocicada e crocante.', 'kg'),
('Beterraba', 'Raiz de cor roxa vibrante e sabor terroso adocicado.', 'kg'),
('Tomate Cereja', 'Pequeno, doce e suculento, perfeito para saladas e snacks.', 'g'),
('Tomate Italiano', 'Formato alongado, pouca semente, ideal para molhos.', 'kg'),
('Pepino Japonês', 'Fino, casca escura e com poucas sementes.', 'unidade'),
('Abobrinha Italiana', 'Versátil, de casca verde e polpa macia.', 'unidade'),
('Cebola Pera', 'Base para muitos pratos, sabor forte e indispensável.', 'kg'),
('Alho', 'Temperos essencial na culinária brasileira.', 'g'),
('Pimentão Verde', 'Sabor mais suave, ideal para refogados e saladas.', 'unidade'),
('Batata Asterix', 'Casca rosada, ideal para frituras e purês.', 'kg');

-- FRUTAS
INSERT INTO produtos (nm_produto, descricao, unidade_medida_padrao) VALUES
('Morango', 'Fruta vermelha, doce e levemente ácida.', 'g'),
('Limão Taiti', 'Fruta cítrica, sem sementes e muito suculenta.', 'kg'),
('Laranja Pera', 'Laranja doce, com bastante caldo, ótima para sucos.', 'kg'),
('Banana Nanica', 'Doce e macia, ideal para consumo in natura e sobremesas.', 'kg');

-- ERVAS E TEMPEROS
INSERT INTO produtos (nm_produto, descricao, unidade_medida_padrao) VALUES
('Salsinha', 'Tempero fresco e aromático, muito usado em finalizações.', 'g'),
('Cebolinha', 'Tempero fresco com sabor suave de cebola.', 'g'),
('Coentro', 'Aroma marcante, muito usado na culinária regional.', 'g'),
('Manjericão', 'Folhas perfumadas, base para molho pesto e pratos italianos.', 'g'),
('Hortelã', 'Aroma refrescante, usado em bebidas, sobremesas e pratos árabes.', 'g');
