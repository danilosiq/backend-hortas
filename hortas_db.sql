CREATE DATABASE IF NOT EXISTS hortas_db;
USE hortas_db;

-- endereco_hortas
CREATE TABLE endereco_hortas (
    id_endereco_hortas INT AUTO_INCREMENT PRIMARY KEY,
    nm_rua VARCHAR(50),
    nr_cep VARCHAR(8),
    nm_bairro VARCHAR(50),
    nm_estado CHAR(2),
    nm_cidade VARCHAR(50),
    nm_pais VARCHAR(20)
);

-- hortas
CREATE TABLE hortas (
    id_hortas INT AUTO_INCREMENT PRIMARY KEY,
    endereco_hortas_id_endereco_hortas INT,
    nr_cnpj VARCHAR(14),
    nome VARCHAR(50),
    descricao VARCHAR(255),
    receitas_geradas BIGINT,
    CONSTRAINT fk_hortas_endereco FOREIGN KEY (endereco_hortas_id_endereco_hortas)
        REFERENCES endereco_hortas(id_endereco_hortas)
        ON DELETE CASCADE ON UPDATE CASCADE
);

-- estoques
CREATE TABLE estoques (
    id_estoques INT AUTO_INCREMENT PRIMARY KEY,
    hortas_id_hortas INT,
    total_itens BIGINT,
    dt_validade DATE,.
    dt_colheita DATE,
    dt_plantio DATE,
    nm_item VARCHAR(100),
    descricao VARCHAR(255),
    ds_quantiade DECIMAL(10,2),
    unidade_medida ENUM('g','kg','ton','unidade'),
    CONSTRAINT fk_estoques_hortas FOREIGN KEY (hortas_id_hortas)
        REFERENCES hortas(id_hortas)
        ON DELETE CASCADE ON UPDATE CASCADE
);

-- produtor
CREATE TABLE produtor (
    id_produtor INT AUTO_INCREMENT PRIMARY KEY,
    hortas_id_hortas INT,
    nome_produtor VARCHAR(50),
    telefone_produtor VARCHAR(15),
    hash_senha VARCHAR(255),
    email_produtor VARCHAR(50),
    nr_cpf VARCHAR(11),
    CONSTRAINT fk_produtor_hortas FOREIGN KEY (hortas_id_hortas)
        REFERENCES hortas(id_hortas)
        ON DELETE CASCADE ON UPDATE CASCADE
);
