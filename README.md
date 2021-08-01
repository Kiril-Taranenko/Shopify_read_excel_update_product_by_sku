# Shopify_read_excel_update_product_by_sku
This is the shopify project to update product available based on reading excel file.
This project is checking excel sheet what providen from manufactures based in real time.
After read excel file extract backorder sku and find product inventory items by those sku.
With those inventory items update inventory item available by using inventoryBulkUpdateAtLocation GraphQL api.
