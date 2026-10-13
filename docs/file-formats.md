# File formats

OpenImport reads CSV, Excel (.xlsx), JSON and XML files without any extra library. The format comes from the
profile, or is detected from the file name and, when the name says nothing, from the first bytes of the file.

Every reader returns rows of text keyed by column name, and every row keeps its position in the file, so an error
says "row 18" and row 18 is what the merchant sees in Excel or a text editor.

## CSV and TSV

| Option | Default | Notes |
|---|---|---|
| Delimiter | auto | Detected from the header line: comma, semicolon, tab or pipe (counted outside quotes). |
| Enclosure | `"` | Doubled enclosures inside a cell (`""`) are one quote. |
| Encoding | auto | UTF-8 (with or without byte order mark), UTF-16 with byte order mark (Excel "Unicode text"), otherwise Windows-1252. Can be set to UTF-8, Windows-1252, ISO-8859-1 or ISO-8859-15. |
| First row is the header | yes | Without a header, columns are named `column_1`, `column_2`, … |

- Quoted cells may hold delimiters and line breaks.
- Empty lines are skipped; row numbers still count them.
- Empty header cells become `column_<n>`; duplicate headers become `name (2)`, `name (3)`.
- Short rows are filled with empty cells; extra cells without a header are dropped.
- The file is streamed: memory does not grow with the file size.

## Excel (.xlsx)

| Option | Default | Notes |
|---|---|---|
| Sheet | first sheet | A sheet name (case does not matter) or its position (1, 2, …). |
| First row is the header | yes | |

- Text, numbers, booleans (`1` / `0`) and the cached results of formulas are read. Error cells (`#N/A`) are empty.
- Numbers come out as Excel shows them, without binary noise (`0.3`, not `0.30000000000000004`).
- Cells with a date or time format become `2024-01-31` or `2024-01-31 14:30:00` (1900 and 1904 date systems).
- Row numbers are the sheet's own row numbers; empty rows are skipped.
- Old Excel files (.xls) are not supported: save them as .xlsx or .csv.
- Workbooks that would unpack to more than 512 MB are refused.
- The sheet is streamed with PHP's XMLReader; only the shared strings table is held in memory.

## JSON

| Option | Default | Notes |
|---|---|---|
| Record path | auto | A dot path to the list of records, e.g. `data.items`. Auto: the document itself when it is a list, otherwise its only list of objects. |

- Nested objects become dotted columns: `{"stock": {"qty": 3}}` gives the column `stock.qty`.
- Lists of plain values are joined with a comma (`["red", "blue"]` gives `red,blue`); lists of objects are kept as
  JSON text in one column, for a transform to pick apart.
- `true` / `false` become `1` / `0`, `null` becomes an empty cell. Very large integers keep every digit.
- JSON files are read into memory, up to 50 MB by default. Use CSV or XML for larger files.

## XML

| Option | Default | Notes |
|---|---|---|
| Record path | children of the root | `/catalog/products/product` (from the root) or `//product` (at any depth). |

- Child elements become columns (`sku`, `stock.qty`), attributes become `@id` or `price.@currency`.
- Repeated plain children are joined with a comma (`<category>Men</category><category>Sale</category>` gives
  `Men,Sale`); repeated children with their own children are kept as JSON text.
- CDATA is read as text.
- The file is streamed record by record: memory does not grow with the file size.
- Files with a DOCTYPE are refused, and the parser never loads anything from the network (no XML entity attacks).

## Column names in JSON and XML files

JSON and XML records do not all need the same fields. The column list shown in the mapping editor comes from the
first 1,000 records; fields that only appear later are still imported when they are mapped by name.
