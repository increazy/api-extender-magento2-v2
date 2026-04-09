# Increazy ApiExtenderV2 - Magento 2

Modulo de integracao backend que sincroniza dados do catalogo e pedidos do Magento 2 com a plataforma Increazy em tempo real via webhooks. Tambem estende as APIs REST nativas do Magento com atributos customizados para enriquecimento de dados de produtos e categorias.

Compativel com Magento 2.3.x ate 2.4.x.

## Instalacao

1. Copie a pasta `Increazy` para `app/code/`.
2. Execute:

```bash
php bin/magento module:enable Increazy_ApiExtenderV2
php bin/magento setup:upgrade
php bin/magento setup:di:compile
php bin/magento setup:static-content:deploy -f
php bin/magento cache:flush
```

## Configuracao

No admin do Magento: **Stores > Configuration > Increazy**

| Campo | Descricao |
|-------|-----------|
| Debug webhooks | Ativar log de todos os webhooks enviados para a Increazy |

## Webhooks

O modulo envia webhooks automaticamente para a plataforma Increazy nos seguintes eventos:

### Catalogo

| Evento | Descricao |
|--------|-----------|
| Produto salvo | Envia dados do produto apos salvar no admin |
| Produto deletado | Notifica remocao de produto |
| Atualizacao de atributos em massa | Trata alteracoes de status em lote |
| Categoria salva | Envia dados da categoria apos salvar |
| Categoria deletada | Notifica remocao de categoria |
| Importacao em lote | Trata imports via bulk (CSV, etc.) |

### Estoque

| Evento | Descricao |
|--------|-----------|
| Alteracao de estoque | Envia webhook quando o estoque e atualizado |
| Atualizacao de source items (MSI) | Intercepta alteracoes de inventario multi-source |

### Pedidos

| Evento | Descricao |
|--------|-----------|
| Pedido criado | Envia dados do pedido ao ser finalizado |
| Item cancelado | Notifica cancelamento de item do pedido |

### Regras promocionais

| Evento | Descricao |
|--------|-----------|
| Regra de catalogo salva | Envia webhook ao criar/editar uma catalog price rule |
| Regra de catalogo deletada | Notifica remocao de catalog price rule |
| Regra de carrinho salva | Envia webhook ao criar/editar uma cart price rule |
| Regra de carrinho deletada | Notifica remocao de cart price rule |

### Precos

| Evento | Descricao |
|--------|-----------|
| Atualizacao de precos | Intercepta alteracoes de preco via API |

## Extensao das APIs REST

O modulo estende as APIs nativas do Magento adicionando o atributo `increazy` nas seguintes entidades:

### Produto (`Magento\Catalog\Api\Data\ProductInterface`)

Dados adicionais retornados:
- Precos (base, especial, regras de catalogo)
- Estoque e quantidade vendavel
- Swatches de atributos configuráveis
- Categorias associadas
- Dados de vendas

### Categoria (`Magento\Catalog\Api\Data\CategoryInterface`)

Dados adicionais retornados:
- Imagens da categoria
- Produtos filhos
- Subcategorias
- Store IDs associados

### Atributos (`Magento\Eav\Api\Data\AttributeInterface`)

Dados adicionais retornados:
- Swatches das opcoes (cores, imagens, texto)

## Estrutura

```
ApiExtenderV2/
├── Attribute/           # Interfaces de atributos e swatches
├── Model/               # WebClient (HTTP), SwatchOption
├── Observer/            # Webhooks (13 observers)
├── Plugin/              # Extensao das APIs REST (4 plugins)
├── etc/
│   ├── adminhtml/       # Config admin + eventos admin
│   ├── extension_attributes.xml
│   ├── events.xml       # Eventos frontend
│   └── di.xml           # Plugins
└── view/                # Templates admin
```
