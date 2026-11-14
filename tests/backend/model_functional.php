<?php

declare(strict_types=1);

define('BASE_URL', 'http://127.0.0.1:8080');

require_once __DIR__ . '/../../mvc/Models/Administrador.php';
require_once __DIR__ . '/../../mvc/Models/Empresa.php';
require_once __DIR__ . '/../../mvc/Models/Entregador.php';
require_once __DIR__ . '/../../mvc/Models/Pedido.php';

use mvc\Models\Administrador;
use mvc\Models\Empresa;
use mvc\Models\Entregador;
use mvc\Models\Pedido;

$checks = 0;

function expect(bool $condition, string $message): void
{
    global $checks;
    $checks++;

    if (!$condition) {
        fwrite(STDERR, "Falha: {$message}\n");
        exit(1);
    }
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->exec(<<<'SQL'
CREATE TABLE empresa (
    id_empresa INTEGER PRIMARY KEY AUTOINCREMENT,
    nome_empresa TEXT NOT NULL,
    CNPJ TEXT NOT NULL,
    nome_usuario TEXT NOT NULL,
    email TEXT NOT NULL,
    senha TEXT NOT NULL,
    nome_arquivo TEXT
);
CREATE TABLE administrador (
    id_adm INTEGER PRIMARY KEY AUTOINCREMENT,
    nome_adm TEXT NOT NULL,
    nome_usuario TEXT NOT NULL,
    nome_foto TEXT,
    senha TEXT NOT NULL,
    FK_EMPRESA_id_empresa INTEGER NOT NULL
);
CREATE TABLE entregador (
    id_entregador INTEGER PRIMARY KEY AUTOINCREMENT,
    nome_completo TEXT NOT NULL,
    CPF TEXT NOT NULL,
    telefone TEXT NOT NULL,
    nome_foto3x4 TEXT,
    email TEXT NOT NULL,
    nome_usuario TEXT NOT NULL,
    senha TEXT NOT NULL,
    nome_cnh TEXT,
    FK_EMPRESA_id_empresa INTEGER NOT NULL,
    latitude REAL,
    longitude REAL
);
CREATE TABLE pedido (
    id_pedido INTEGER PRIMARY KEY AUTOINCREMENT,
    nome_cliente TEXT NOT NULL,
    preco REAL NOT NULL,
    endereco TEXT NOT NULL,
    bairro TEXT NOT NULL,
    descricao TEXT,
    id_empresa INTEGER NOT NULL,
    id_adm INTEGER NOT NULL,
    latitude REAL,
    longitude REAL,
    status TEXT NOT NULL,
    id_entregador INTEGER
);
SQL);

$empresa = new Empresa($pdo);
expect($empresa->create([
    'nome_empresa' => 'GUIAR Teste',
    'cnpj' => '00.000.000/0001-00',
    'nome_usuario' => 'empresa_ci',
    'email' => 'empresa-ci@example.com',
    'senha' => 'senha-teste',
    'nome_arquivo' => 'logo teste.png',
]), 'Empresa::create deve cadastrar uma empresa.');
$empresaId = (int) $pdo->lastInsertId();
expect($empresa->create([
    'nome_empresa' => 'Outra Empresa',
    'cnpj' => '11.111.111/0001-11',
    'nome_usuario' => 'outra_empresa',
    'email' => 'outra@example.com',
    'senha' => 'outra-senha',
    'nome_arquivo' => null,
]), 'Empresa::create deve permitir empresa sem logo.');
$outraEmpresaId = (int) $pdo->lastInsertId();
$empresaLogin = $empresa->login('empresa_ci', 'senha-teste');
expect(is_array($empresaLogin) && (int) $empresaLogin['id_empresa'] === $empresaId, 'Empresa::login deve autenticar credenciais válidas.');
expect($empresa->login('empresa_ci', 'incorreta') === false, 'Empresa::login deve rejeitar senha incorreta.');
expect($empresa->getById($empresaId)['email'] === 'empresa-ci@example.com', 'Empresa::getById deve retornar os dados cadastrados.');
expect($empresa->emailExists('empresa-ci@example.com'), 'Empresa::emailExists deve localizar e-mails cadastrados.');
expect(!$empresa->emailExists('inexistente@example.com'), 'Empresa::emailExists deve retornar falso para e-mail inexistente.');
expect(
    $empresa->getLogoUrlById($empresaId) === BASE_URL . '/public/uploads/empresas/logo%20teste.png',
    'Empresa::getLogoUrlById deve codificar o nome do arquivo.',
);
expect(
    $empresa->getLogoUrlById($outraEmpresaId) === BASE_URL . '/img/LogoPreto.png',
    'Empresa::getLogoUrlById deve usar a imagem padrão quando não há logo.',
);

$administrador = new Administrador($pdo);
expect($administrador->create([
    'nome_adm' => 'Admin CI',
    'nome_usuario' => 'admin_ci',
    'nome_foto' => 'foto perfil.png',
    'senha' => 'senha-admin',
    'company_id' => $empresaId,
]), 'Administrador::create deve cadastrar um administrador.');
$administradorId = (int) $pdo->lastInsertId();
expect(count($administrador->getByEmpresaId($empresaId)) === 1, 'Administrador::getByEmpresaId deve filtrar pela empresa.');
$administradorLogin = $administrador->login('admin_ci', 'senha-admin');
expect(is_array($administradorLogin) && (int) $administradorLogin['id_adm'] === $administradorId, 'Administrador::login deve autenticar credenciais válidas.');
expect($administrador->login('admin_ci', 'incorreta') === false, 'Administrador::login deve rejeitar senha incorreta.');
expect($administrador->getById($administradorId)['nome_adm'] === 'Admin CI', 'Administrador::getById deve retornar o administrador.');
expect(
    $administrador->getPhotoUrlForCompany($administradorId, $empresaId)
        === BASE_URL . '/public/uploads/empresas/GUIAR%20Teste/foto%20perfil.png',
    'Administrador::getPhotoUrlForCompany deve montar a URL da foto da empresa.',
);
expect($administrador->getPhotoUrlForCompany($administradorId, $outraEmpresaId) === '', 'Administrador::getPhotoUrlForCompany não deve expor foto de outra empresa.');
expect($administrador->update($administradorId, [
    'nome_adm' => 'Admin Atualizado',
    'nome_usuario' => 'admin_atualizado',
    'senha' => 'senha-nova',
    'nome_foto' => '',
]), 'Administrador::update deve atualizar os dados sem substituir a foto.');
expect($administrador->getById($administradorId)['nome_foto'] === 'foto perfil.png', 'Administrador::update deve preservar a foto quando não há nova imagem.');
expect($administrador->update($administradorId, [
    'nome_adm' => 'Admin Atualizado',
    'nome_usuario' => 'admin_atualizado',
    'senha' => 'senha-nova',
    'nome_foto' => 'foto nova.png',
]), 'Administrador::update deve aceitar nova foto.');
expect($administrador->getById($administradorId)['nome_foto'] === 'foto nova.png', 'Administrador::update deve persistir a nova foto.');

$entregador = new Entregador($pdo);
expect($entregador->create([
    'nome_completo' => 'Entregador CI',
    'cpf' => '00000000000',
    'telefone' => '11999999999',
    'foto_3x4' => 'entregador.png',
    'email' => 'entregador@example.com',
    'nome_usuario' => 'entregador_ci',
    'senha' => 'senha-entregador',
    'foto_CNH' => 'cnh.png',
    'company_id' => $empresaId,
]), 'Entregador::create deve cadastrar um entregador.');
$entregadorId = (int) $pdo->lastInsertId();
expect(count($entregador->getAllByEmpresa($empresaId)) === 1, 'Entregador::getAllByEmpresa deve filtrar pela empresa.');
$entregadorLogin = $entregador->login('entregador@example.com', 'senha-entregador');
expect(is_array($entregadorLogin) && (int) $entregadorLogin['id_entregador'] === $entregadorId, 'Entregador::login deve autenticar credenciais válidas.');
expect($entregador->login('entregador@example.com', 'incorreta') === false, 'Entregador::login deve rejeitar senha incorreta.');
expect($entregador->getById($entregadorId)['foto_3x4'] === 'entregador.png', 'Entregador::getById deve retornar os dados do perfil.');
expect($entregador->update($entregadorId, $empresaId, [
    'nome_completo' => 'Entregador Atualizado',
    'cpf' => '00000000000',
    'telefone' => '11888888888',
    'email' => 'entregador-atualizado@example.com',
]), 'Entregador::update deve atualizar um entregador da empresa.');
expect($entregador->getById($entregadorId)['email'] === 'entregador-atualizado@example.com', 'Entregador::update deve persistir os dados atualizados.');
expect($entregador->update($entregadorId, $outraEmpresaId, [
    'nome_completo' => 'Não deve atualizar',
    'cpf' => '00000000000',
    'telefone' => '11000000000',
    'email' => 'nao-atualizar@example.com',
]), 'Entregador::update deve executar sem erro quando o entregador não pertence à empresa.');
expect($entregador->getById($entregadorId)['email'] === 'entregador-atualizado@example.com', 'Entregador::update não deve alterar entregador de outra empresa.');
expect($entregador->updateLocation($entregadorId, -22.591, -46.523), 'Entregador::updateLocation deve atualizar coordenadas.');
expect(
    (float) $pdo->query("SELECT latitude FROM entregador WHERE id_entregador = {$entregadorId}")->fetchColumn() === -22.591,
    'Entregador::updateLocation deve persistir latitude.',
);
expect($entregador->updateProfile($entregadorId, [
    'nome_completo' => 'Entregador Atualizado',
    'cpf' => '00000000000',
    'telefone' => '11777777777',
    'email' => 'perfil@example.com',
    'nome_usuario' => 'entregador_perfil',
    'senha' => 'senha-perfil',
    'foto_3x4' => '',
    'foto_CNH' => '',
]), 'Entregador::updateProfile deve atualizar o perfil sem novas imagens.');
expect($entregador->getById($entregadorId)['foto_3x4'] === 'entregador.png', 'Entregador::updateProfile deve preservar fotos não substituídas.');
expect($entregador->updateProfile($entregadorId, [
    'nome_completo' => 'Entregador Atualizado',
    'cpf' => '00000000000',
    'telefone' => '11777777777',
    'email' => 'perfil@example.com',
    'nome_usuario' => 'entregador_perfil',
    'senha' => 'senha-perfil',
    'foto_3x4' => 'foto-nova.png',
    'foto_CNH' => 'cnh-nova.png',
]), 'Entregador::updateProfile deve aceitar novas imagens.');
expect($entregador->getById($entregadorId)['foto_CNH'] === 'cnh-nova.png', 'Entregador::updateProfile deve persistir a nova CNH.');

$pedido = new Pedido($pdo);
$pedidoIds = [];
foreach ([
    ['Cliente Pendente', $empresaId],
    ['Cliente Em Rota', $empresaId],
    ['Cliente Entregue', $empresaId],
    ['Cliente Outra Empresa', $outraEmpresaId],
] as [$cliente, $companyId]) {
    expect($pedido->create([
        'nome_cliente' => $cliente,
        'preco' => 25.50,
        'endereco' => 'Rua de teste, 1',
        'bairro' => 'Centro',
        'descricao' => 'Pedido funcional',
        'id_empresa' => $companyId,
        'id_adm' => $administradorId,
        'latitude' => -22.591,
        'longitude' => -46.523,
    ]), "Pedido::create deve cadastrar {$cliente}.");
    $pedidoIds[] = (int) $pdo->lastInsertId();
}
[$pendenteId, $emRotaId, $entregueId, $outraEmpresaPedidoId] = $pedidoIds;
expect($pedido->getById($pendenteId)['status'] === 'Pendente', 'Pedido::create deve iniciar pedidos como pendentes.');
expect($pedido->update($pendenteId, [
    'nome_cliente' => 'Cliente Editado',
    'preco' => 30.00,
    'endereco' => 'Rua Atualizada, 2',
    'bairro' => 'Centro',
    'descricao' => 'Descrição atualizada',
    'latitude' => -22.600,
    'longitude' => -46.530,
]), 'Pedido::update deve atualizar os dados do pedido.');
expect($pedido->getById($pendenteId)['nome_cliente'] === 'Cliente Editado', 'Pedido::update deve persistir as alterações.');
expect($pedido->assignToEntregador([$emRotaId, $entregueId], $entregadorId), 'Pedido::assignToEntregador deve atribuir pedidos ao entregador.');
expect($pedido->updateStatus($entregueId, 'entregue'), 'Pedido::updateStatus deve atualizar o status.');
expect(count($pedido->getAllByEmpresa($empresaId)) === 3, 'Pedido::getAllByEmpresa deve filtrar pela empresa.');
expect(count($pedido->getAllByEmpresa($empresaId, 'entregue')) === 1, 'Pedido::getAllByEmpresa deve filtrar entregues.');
expect(count($pedido->getAllByEmpresa($empresaId, 'pendente')) === 2, 'Pedido::getAllByEmpresa deve excluir entregues do filtro pendente.');
expect($pedido->getById($emRotaId)['status'] === 'A caminho', 'Pedido::assignToEntregador deve colocar o pedido em rota.');
expect(count($pedido->getAllByEntregador($entregadorId)) === 1, 'Pedido::getAllByEntregador deve excluir pedidos entregues.');
expect(
    (int) $pedido->getDashboardStats($empresaId)['total'] === 3
        && (int) $pedido->getDashboardStats($empresaId)['entregues'] === 1
        && (int) $pedido->getDashboardStats($empresaId)['em_andamento'] === 1
        && (int) $pedido->getDashboardStats($empresaId)['pendentes'] === 1,
    'Pedido::getDashboardStats deve contar os pedidos por status.',
);
$recentes = $pedido->getRecent($empresaId, 2);
expect(count($recentes) === 2 && (int) $recentes[0]['id_pedido'] > (int) $recentes[1]['id_pedido'], 'Pedido::getRecent deve respeitar limite e ordem decrescente.');
expect($recentes[0]['nome_entregador'] === 'Entregador Atualizado', 'Pedido::getRecent deve incluir o nome do entregador.');
expect(
    (int) $entregador->getAllByEmpresaComContagem($empresaId, 1)[0]['total_entregas'] === 1,
    'Entregador::getAllByEmpresaComContagem deve contar entregas concluídas.',
);
expect($pedido->deleteDeliveredByEmpresa($empresaId), 'Pedido::deleteDeliveredByEmpresa deve excluir entregues da empresa.');
expect($pedido->getById($entregueId) === false, 'Pedido::deleteDeliveredByEmpresa deve remover pedidos entregues.');
expect($pedido->getById($emRotaId) !== false, 'Pedido::deleteDeliveredByEmpresa deve preservar pedidos em rota.');
expect($pedido->getById($pendenteId) !== false, 'Pedido::deleteDeliveredByEmpresa deve preservar pedidos pendentes.');
expect($pedido->getById($outraEmpresaPedidoId) !== false, 'Pedido::deleteDeliveredByEmpresa não deve remover pedidos de outra empresa.');
expect($pedido->delete($pendenteId), 'Pedido::delete deve excluir um pedido.');
expect($pedido->getById($pendenteId) === false, 'Pedido::delete deve remover o pedido do banco.');

expect($entregador->delete($entregadorId, $outraEmpresaId), 'Entregador::delete deve executar sem erro para outra empresa.');
expect($entregador->getById($entregadorId) !== false, 'Entregador::delete não deve remover entregador de outra empresa.');
expect($entregador->delete($entregadorId, $empresaId), 'Entregador::delete deve remover entregador da empresa.');
expect($entregador->getById($entregadorId) === false, 'Entregador::delete deve remover o registro.');

fwrite(STDOUT, "Testes funcionais do backend concluídos: {$checks} verificações.\n");
