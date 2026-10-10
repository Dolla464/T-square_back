"""Build pharma-treasury-egp.xlsx per treasury plan (CMO + distribution, EGP)."""
from __future__ import annotations

import re
from pathlib import Path

from openpyxl import Workbook
from openpyxl.workbook.properties import CalcProperties
from openpyxl.styles import Alignment, Font, PatternFill
from openpyxl.utils import get_column_letter
from openpyxl.worksheet.datavalidation import DataValidation
from openpyxl.worksheet.table import Table, TableStyleInfo
from openpyxl.worksheet.protection import SheetProtection

OUT = Path(__file__).resolve().parent / "pharma-treasury-egp.xlsx"

HEADER_FILL = PatternFill("solid", fgColor="1F4E79")
HEADER_FONT = Font(color="FFFFFF", bold=True)
RTL = Alignment(horizontal="right", vertical="center", readingOrder=2)

_XLWS_FUNCS = ("FILTER", "SORT", "VSTACK", "XLOOKUP", "UNIQUE", "SEQUENCE")
_XLFN_FUNCS = ("LET",)


def xl_formula(formula: str) -> str:
    """Prefix dynamic-array functions so Excel does not strip formulas on open."""
    body = formula[1:] if formula.startswith("=") else formula
    for fn in _XLWS_FUNCS:
        body = re.sub(rf"\b{fn}\(", f"_xlfn._xlws.{fn}(", body)
    for fn in _XLFN_FUNCS:
        body = re.sub(rf"\b{fn}\(", f"_xlfn.{fn}(", body)
    return f"={body}"


def set_formula(cell, formula: str) -> None:
    cell.value = xl_formula(formula) if formula.startswith("=") else xl_formula(f"={formula}")


def approved_move_filter(table: str) -> str:
    return (
        f"({table}[حالة_الاعتماد]=\"معتمد\")*({table}[حالة_السجل]<>\"ملغاة\")"
    )


def vstack_approved() -> str:
    return (
        f"VSTACK(FILTER(tblMove,{approved_move_filter('tblMove')}),"
        f"FILTER(tblXfer,{approved_move_filter('tblXfer')}))"
    )


def rtl_sheet(ws):
    ws.sheet_view.rightToLeft = True


def style_header_row(ws, row: int, ncol: int):
    for c in range(1, ncol + 1):
        cell = ws.cell(row=row, column=c)
        cell.fill = HEADER_FILL
        cell.font = HEADER_FONT
        cell.alignment = RTL


def add_table(ws, name: str, ref: str):
    tab = Table(displayName=name, ref=ref)
    tab.tableStyleInfo = TableStyleInfo(
        name="TableStyleMedium2",
        showFirstColumn=False,
        showLastColumn=False,
        showRowStripes=True,
        showColumnStripes=False,
    )
    ws.add_table(tab)


def build_readme(wb: Workbook):
    ws = wb.active
    ws.title = "README"
    rtl_sheet(ws)
    lines = [
        "نظام حركة خزينة — شركة أدوية بشرية (CMO + توزيع)",
        "",
        "1) أدخل الحركات في «حركات_يومية» ثم «معتمد» بعد التحقق (يسمح_بالاعتماد = نعم).",
        "2) التحويلات في «تحويلات» — سطران لكل TRX (تحويل_داخلي، لا تدفق تجاري).",
        "3) الشيكات: استلام (تدفق تجاري) → إيداع → تحصيل (تحويلات فقط).",
        "4) «دفتر_الحركات_المعتمدة» = FILTER — لا تعديل يدوي.",
        "5) العكس: TRX جديد نوع_الحركة=عكس؛ الأصل → معكوسة + يؤثر_في_الرصيد=لا.",
        "6) Excel 365 مطلوب (FILTER, XLOOKUP, SORT, SCAN).",
        "",
        "نسخ احتياطي أسبوعي. لا P&L — استخدم «تحليل_تدفقات_نقدية».",
    ]
    for i, line in enumerate(lines, 1):
        ws.cell(i, 1, line).alignment = Alignment(wrap_text=True, readingOrder=2)
    ws.column_dimensions["A"].width = 100


def build_settings(wb: Workbook):
    ws = wb.create_sheet("الإعدادات")
    rtl_sheet(ws)
    data = [
        ("اسم_الشركة", "شركة الأدوية — مثال"),
        ("السنة_المالية", 2026),
        ("العملة", "EGP"),
        ("حد_موافقة_تلقائي", 5000),
        ("حد_يتطلب_مدير_مالي", 50000),
        ("حد_يتطلب_مدير_عام", 200000),
        ("بادئة_TRX", "TRX-"),
        ("TRX_التالي", 1),
    ]
    ws["A1"], ws["B1"] = "المفتاح", "القيمة"
    style_header_row(ws, 1, 2)
    for r, (k, v) in enumerate(data, 2):
        ws.cell(r, 1, k)
        ws.cell(r, 2, v)
    ws.column_dimensions["A"].width = 28
    ws.column_dimensions["B"].width = 40


def build_tax(wb: Workbook):
    ws = wb.create_sheet("قواعد_ضريبية")
    rtl_sheet(ws)
    headers = ["كود_معالجة_ضريبية", "الاسم", "نوع", "القيمة", "ملاحظة"]
    rows = [
        ("VAT_STD", "ض.ق.م قياسي", "نسبة", 0.14, "عدّل النسبة — لا افتراض ثابت"),
        ("VAT_ZERO", "ضريبة صفر", "نسبة", 0, ""),
        ("EXEMPT", "معفى", "معفى", 0, ""),
        ("OUT_SCOPE", "خارج النطاق", "—", 0, "تحويلات"),
    ]
    for c, h in enumerate(headers, 1):
        ws.cell(1, c, h)
    style_header_row(ws, 1, len(headers))
    for r, row in enumerate(rows, 2):
        for c, val in enumerate(row, 1):
            ws.cell(r, c, val)
    add_table(ws, "tblTax", f"A1:E{1 + len(rows)}")
    ws.column_dimensions["A"].width = 22


def build_categories(wb: Workbook):
    ws = wb.create_sheet("التصنيفات")
    rtl_sheet(ws)
    headers = [
        "كود_التصنيف",
        "اسم_التصنيف",
        "مركز_التكلفة",
        "نوع_تدفق",
        "يظهر_في_تحليل_التدفق",
    ]
    rows = [
        ("REV-SALES", "تحصيل مبيعات", "مبيعات", "تشغيلي", "نعم"),
        ("REV-OTHER", "إيرادات أخرى", "إداري", "تشغيلي", "نعم"),
        ("REV-RETURN", "مردودات مبيعات", "مبيعات", "تشغيلي", "نعم"),
        ("EXP-CMO", "تصنيع تعاقدي CMO", "تعاقد_تصنيع", "تشغيلي", "نعم"),
        ("EXP-PRODUCT", "شراء منتج تام", "تعاقد_تصنيع", "تشغيلي", "نعم"),
        ("EXP-QA", "تحليل وثبات", "جودة", "تشغيلي", "نعم"),
        ("EXP-EDA", "رسوم EDA / تراخيص", "تنظيمي", "تشغيلي", "نعم"),
        ("EXP-LOG", "تخزين ونقل", "لوجistics", "تشغيلي", "نعم"),
        ("EXP-MKT", "تسويق وعينات", "تسويق", "تشغيلي", "نعم"),
        ("EXP-ADMIN", "إداري ورواتب", "إداري", "تشغيلي", "نعم"),
        ("TRF-INT", "تحويل داخلي", "—", "داخلي", "لا"),
    ]
    for c, h in enumerate(headers, 1):
        ws.cell(1, c, h)
    style_header_row(ws, 1, len(headers))
    for r, row in enumerate(rows, 2):
        for c, val in enumerate(row, 1):
            ws.cell(r, c, val)
    add_table(ws, "tblCat", f"A1:E{1 + len(rows)}")


def build_analytical(wb: Workbook):
    ws = wb.create_sheet("حسابات_تحليلية")
    rtl_sheet(ws)
    headers = ["كود_تحليلي", "اسم_الحساب_المقابل", "نوع"]
    rows = [
        ("AN-AR", "تحصيلات_العميل", "تحصيلات_عملاء"),
        ("AN-AR-REC", "ذمم_تحصيل_عملاء", "ذمم"),
        ("AN-EXP-CMO", "مصروف_CMO_تحليلي", "مصروف"),
        ("AN-EXP-OPS", "مصروف_تشغيل_تحليلي", "مصروف"),
        ("AN-TRF", "تحويل_داخلي_تحليلي", "داخلي"),
    ]
    for c, h in enumerate(headers, 1):
        ws.cell(1, c, h)
    style_header_row(ws, 1, len(headers))
    for r, row in enumerate(rows, 2):
        for c, val in enumerate(row, 1):
            ws.cell(r, c, val)
    add_table(ws, "tblAnalytical", f"A1:C{1 + len(rows)}")


def build_accounts(wb: Workbook):
    ws = wb.create_sheet("الحسابات")
    rtl_sheet(ws)
    headers = [
        "كود_الحساب",
        "اسم_الحساب",
        "نوع_حساب",
        "رصيد_افتتاحي",
        "يُدرج_في_السيولة_المتاحة",
        "نشط",
    ]
    rows = [
        ("ACC-CASH", "خزينة نقدية", "نقدي", 0, "نعم", "نعم"),
        ("ACC-CHK-IN", "شيكات_واردة_بالخزينة", "وسيط_شيك", 0, "لا", "نعم"),
        ("ACC-CHK-DEP", "شيكات_مودعة_تحت_التحصيل", "وسيط_شيك", 0, "لا", "نعم"),
        ("ACC-BANK", "بنك_متاح", "بنك", 0, "نعم", "نعم"),
        ("ACC-BANK-ESC", "بنك_ضمانات", "بنك", 0, "لا", "نعم"),
        ("ACC-PETTY", "عهدة_مندوبي_مبيعات", "نقدي", 0, "لا", "نعم"),
    ]
    for c, h in enumerate(headers, 1):
        ws.cell(1, c, h)
    style_header_row(ws, 1, len(headers))
    for r, row in enumerate(rows, 2):
        for c, val in enumerate(row, 1):
            ws.cell(r, c, val)
    add_table(ws, "tblAccounts", f"A1:F{1 + len(rows)}")


def build_contra(wb: Workbook):
    ws = wb.create_sheet("دليل_حسابات_مقابل")
    rtl_sheet(ws)
    headers = ["مفتاح_الحساب_المقابل", "كود_التصنيف", "النوع", "كود_معالجة_ضريبية", "كود_تحليلي"]
    rows = [
        ("REV-SALES|قبض|VAT_STD", "REV-SALES", "قبض", "VAT_STD", "AN-AR"),
        ("REV-SALES|قبض|VAT_ZERO", "REV-SALES", "قبض", "VAT_ZERO", "AN-AR"),
        ("REV-SALES|قبض|EXEMPT", "REV-SALES", "قبض", "EXEMPT", "AN-AR"),
        ("REV-OTHER|قبض|OUT_SCOPE", "REV-OTHER", "قبض", "OUT_SCOPE", "AN-AR"),
        ("EXP-CMO|صرف|VAT_STD", "EXP-CMO", "صرف", "VAT_STD", "AN-EXP-CMO"),
        ("EXP-PRODUCT|صرف|VAT_STD", "EXP-PRODUCT", "صرف", "VAT_STD", "AN-EXP-CMO"),
        ("EXP-QA|صرف|VAT_STD", "EXP-QA", "صرف", "VAT_STD", "AN-EXP-OPS"),
        ("EXP-EDA|صرف|OUT_SCOPE", "EXP-EDA", "صرف", "OUT_SCOPE", "AN-EXP-OPS"),
        ("EXP-LOG|صرف|VAT_STD", "EXP-LOG", "صرف", "VAT_STD", "AN-EXP-OPS"),
        ("EXP-MKT|صرف|VAT_STD", "EXP-MKT", "صرف", "VAT_STD", "AN-EXP-OPS"),
        ("EXP-ADMIN|صرف|OUT_SCOPE", "EXP-ADMIN", "صرف", "OUT_SCOPE", "AN-EXP-OPS"),
        ("TRF-INT|تحويل_داخلي|OUT_SCOPE", "TRF-INT", "تحويل_داخلي", "OUT_SCOPE", "AN-TRF"),
    ]
    for c, h in enumerate(headers, 1):
        ws.cell(1, c, h)
    style_header_row(ws, 1, len(headers))
    for r, row in enumerate(rows, 2):
        for c, val in enumerate(row, 1):
            ws.cell(r, c, val)
    add_table(ws, "tblContra", f"A1:E{1 + len(rows)}")
    ws.cell(20, 1, "تحقق_تفرد_المفتاح:")
    set_formula(
        ws.cell(20, 2),
        '=IF(COUNTA(tblContra[مفتاح_الحساب_المقابل])=SUMPRODUCT(1/COUNTIF(tblContra[مفتاح_الحساب_المقابل],tblContra[مفتاح_الحساب_المقابل]&"")),"OK","مفتاح مكرر")',
    )


def build_parties(wb: Workbook):
    ws = wb.create_sheet("الجهات")
    rtl_sheet(ws)
    headers = ["كود_الجهة", "الاسم", "نوع_الطرف", "قناة_افتراضية", "نشط"]
    rows = [
        ("P001", "صيدلية النور — مثال", "عميل", "صيدليات", "نعم"),
        ("P002", "موزع القاهرة — مثال", "عميل", "موزعون", "نعم"),
        ("V001", "CMO Pharma — مثال", "CMO", "—", "نعم"),
    ]
    for c, h in enumerate(headers, 1):
        ws.cell(1, c, h)
    style_header_row(ws, 1, len(headers))
    for r, row in enumerate(rows, 2):
        for c, val in enumerate(row, 1):
            ws.cell(r, c, val)
    add_table(ws, "tblParties", f"A1:E{1 + len(rows)}")


MOVE_HEADERS = [
    "رقم_الحركة",
    "رقم_نسخة",
    "التاريخ",
    "نوع_الحركة",
    "الحساب",
    "مدين",
    "دائن",
    "كود_التصنيف",
    "كود_معالجة_ضريبية",
    "قبل_الضريبة",
    "مبلغ_الضريبة",
    "الإجمالي",
    "الجهة",
    "نوع_الطرف",
    "قناة_البيع",
    "مرجع",
    "طريقة_الدفع",
    "رقم_شيك",
    "نوع_تدفق",
    "يؤثر_في_الرصيد",
    "يُحسب_في_التدفق_التجاري",
    "حالة_السجل",
    "مرجع_الحركة_الأصلية",
    "مُعكَس_بواسطة",
    "مفتاح_المقابل",
    "عدد_تطابقات",
    "خطأ_المقابل",
    "الحساب_المقابل",
    "يسمح_بالاعتماد",
    "حالة_الاعتماد",
    "الوصف",
]


def col_index_map(headers):
    return {h: i + 1 for i, h in enumerate(headers)}


def build_movements(wb: Workbook):
    ws = wb.create_sheet("حركات_يومية")
    rtl_sheet(ws)
    for c, h in enumerate(MOVE_HEADERS, 1):
        ws.cell(1, c, h)
    style_header_row(ws, 1, len(MOVE_HEADERS))
    # 500 input rows with formulas from row 2
    last = 152
    add_table(ws, "tblMove", f"A1:{get_column_letter(len(MOVE_HEADERS))}{last}")
    m = col_index_map(MOVE_HEADERS)
    for r in range(2, last + 1):
        ws.cell(row=r, column=m["مدين"]).value = (
            f'=IF([@يؤثر_في_الرصيد]<>"نعم",0,IF([@حالة_السجل]="معكوسة",0,'
            f'IF([@نوع_الحركة]="قبض",[@الإجمالي],0)))'
        )
        ws.cell(row=r, column=m["دائن"]).value = (
            f'=IF([@يؤثر_في_الرصيد]<>"نعم",0,IF([@حالة_السجل]="معكوسة",0,'
            f'IF([@نوع_الحركة]="صرف",[@الإجمالي],IF([@نوع_الحركة]="عكس",[@الإجمالي],0))))'
        )
        ws.cell(row=r, column=m["مفتاح_المقابل"]).value = (
            f'=IF([@كود_التصنيف]="","",[@كود_التصنيف]&"|"&[@نوع_الحركة]&"|"&[@كود_معالجة_ضريبية])'
        )
        ws.cell(row=r, column=m["عدد_تطابقات"]).value = (
            f'=IF([@مفتاح_المقابل]="",0,COUNTIF(tblContra[مفتاح_الحساب_المقابل],[@مفتاح_المقابل]))'
        )
        ws.cell(row=r, column=m["خطأ_المقابل"]).value = (
            f'=IF([@مفتاح_المقابل]="","",IF([@عدد_تطابقات]=0,"لا تطابق في دليل المقابل",'
            f'IF([@عدد_تطابقات]>1,"تطابق مكرر في الدليل","")))'
        )
        set_formula(
            ws.cell(row=r, column=m["الحساب_المقابل"]),
            'IF([@عدد_تطابقات]<>1,"",XLOOKUP([@مفتاح_المقابل],tblContra[مفتاح_الحساب_المقابل],tblContra[كود_تحليلي],""))',
        )
        ws.cell(row=r, column=m["يسمح_بالاعتماد"]).value = (
            f'=IF(AND([@خطأ_المقابل]="",[@الحساب]<>"",[@كود_التصنيف]<>"",[@الإجمالي]>0),"نعم","لا")'
        )
        set_formula(
            ws.cell(row=r, column=m["مبلغ_الضريبة"]),
            'IF([@كود_معالجة_ضريبية]="",0,IF(XLOOKUP([@كود_معالجة_ضريبية],tblTax[كود_معالجة_ضريبية],tblTax[نوع],"")="نسبة",'
            '[@قبل_الضريبة]*XLOOKUP([@كود_معالجة_ضريبية],tblTax[كود_معالجة_ضريبية],tblTax[القيمة],0),0))',
        )
        ws.cell(row=r, column=m["الإجمالي"]).value = f"=[@قبل_الضريبة]+[@مبلغ_الضريبة]"
        set_formula(
            ws.cell(row=r, column=m["نوع_تدفق"]),
            'IF([@كود_التصنيف]="","",XLOOKUP([@كود_التصنيف],tblCat[كود_التصنيف],tblCat[نوع_تدفق],""))',
        )
    # validations
    dv_status = DataValidation(type="list", formula1='"مسودة,معلق,معتمد"', allow_blank=True)
    dv_movetype = DataValidation(type="list", formula1='"قبض,صرف,عكس"', allow_blank=True)
    dv_yesno = DataValidation(type="list", formula1='"نعم,لا"', allow_blank=True)
    ws.add_data_validation(dv_status)
    ws.add_data_validation(dv_movetype)
    ws.add_data_validation(dv_yesno)
    col_status = get_column_letter(m["حالة_الاعتماد"])
    col_type = get_column_letter(m["نوع_الحركة"])
    col_impact = get_column_letter(m["يؤثر_في_الرصيد"])
    col_flow = get_column_letter(m["يُحسب_في_التدفق_التجاري"])
    dv_status.add(f"{col_status}2:{col_status}{last}")
    dv_movetype.add(f"{col_type}2:{col_type}{last}")
    for col in (col_impact, col_flow, get_column_letter(m["حالة_السجل"])):
        dv_yesno.add(f"{col}2:{col}{last}")


def build_transfers(wb: Workbook):
    ws = wb.create_sheet("تحويلات")
    rtl_sheet(ws)
    headers = list(MOVE_HEADERS)
    for c, h in enumerate(headers, 1):
        ws.cell(1, c, h)
    style_header_row(ws, 1, len(headers))
    last = 52
    add_table(ws, "tblXfer", f"A1:{get_column_letter(len(headers))}{last}")
    ws["AF2"] = "تعليمات: أدخل سطرين لكل TRX (دائن من / مدين إلى) — نوع_الحركة=تحويل_داخلي"
    m = col_index_map(headers)
    for r in range(2, last + 1):
        ws.cell(row=r, column=m["كود_التصنيف"], value="TRF-INT")
        ws.cell(row=r, column=m["كود_معالجة_ضريبية"], value="OUT_SCOPE")
        ws.cell(row=r, column=m["نوع_الحركة"], value="تحويل_داخلي")
        ws.cell(row=r, column=m["يُحسب_في_التدفق_التجاري"], value="لا")
        ws.cell(row=r, column=m["يؤثر_في_الرصيد"], value="نعم")
        ws.cell(row=r, column=m["حالة_السجل"], value="معتمد")
        ws.cell(row=r, column=m["نوع_تدفق"], value="داخلي")
        ws.cell(row=r, column=m["مفتاح_المقابل"]).value = (
            f'=IF([@كود_التصنيف]="","",[@كود_التصنيف]&"|"&[@نوع_الحركة]&"|"&[@كود_معالجة_ضريبية])'
        )
        ws.cell(row=r, column=m["عدد_تطابقات"]).value = (
            f'=IF([@مفتاح_المقابل]="",0,COUNTIF(tblContra[مفتاح_الحساب_المقابل],[@مفتاح_المقابل]))'
        )
        ws.cell(row=r, column=m["خطأ_المقابل"]).value = (
            f'=IF([@مفتاح_المقابل]="","",IF([@عدد_تطابقات]=0,"لا تطابق في دليل المقابل",'
            f'IF([@عدد_تطابقات]>1,"تطابق مكرر في الدليل","")))'
        )
        set_formula(
            ws.cell(row=r, column=m["الحساب_المقابل"]),
            'IF([@عدد_تطابقات]<>1,"",XLOOKUP([@مفتاح_المقابل],tblContra[مفتاح_الحساب_المقابل],tblContra[كود_تحليلي],""))',
        )
        ws.cell(row=r, column=m["يسمح_بالاعتماد"]).value = (
            f'=IF(AND([@خطأ_المقابل]="",[@الحساب]<>"",OR([@مدين]>0,[@دائن]>0)),"نعم","لا")'
        )


def build_checks(wb: Workbook):
    ws = wb.create_sheet("شيكات")
    rtl_sheet(ws)
    headers = [
        "رقم_الشيك",
        "TRX_استلام",
        "TRX_إيداع",
        "TRX_تحصيل",
        "الجهة",
        "المبلغ",
        "تاريخ_الاستحقاق",
        "الحالة",
        "ملاحظة",
    ]
    for c, h in enumerate(headers, 1):
        ws.cell(1, c, h)
    style_header_row(ws, 1, len(headers))
    add_table(ws, "tblChecks", f"A1:I2")
    dv = DataValidation(
        type="list",
        formula1='"في_الخزينة,مودع_تحت_التحصيل,محصل,مرتد"',
        allow_blank=True,
    )
    ws.add_data_validation(dv)
    dv.add("H2:H500")


def build_ledger(wb: Workbook):
    ws = wb.create_sheet("دفتر_الحركات_المعتمدة")
    rtl_sheet(ws)
    ws["A1"] = "سجل معتمد (FILTER) — لا تعديل يدوي"
    headers = MOVE_HEADERS + ["رقم_سطر_القيد", "رصيد_بعد_الحركة"]
    start = 3
    for c, h in enumerate(headers, 1):
        ws.cell(start, c, h)
    style_header_row(ws, start, len(headers))
    ncol = len(headers)
    last_col = get_column_letter(ncol)
    # spill FILTER — row 4
    move_cols = len(MOVE_HEADERS)
    move_range = f"tblMove[[#Headers],[{MOVE_HEADERS[0]}]:[{MOVE_HEADERS[-1]}]]"
    xfer_range = "tblXfer"
    set_formula(ws.cell(start + 1, 1), f"IFERROR({vstack_approved()},\"\")")


def build_sorted_ledger(wb: Workbook):
    ws = wb.create_sheet("_دفتر_مرتب")
    rtl_sheet(ws)
    ws.sheet_state = "hidden"
    ws["A1"] = "ترتيب: حساب → تاريخ → TRX"
    headers = MOVE_HEADERS + ["رقم_سطر_القيد", "رصيد_بعد_الحركة", "صافي_الحركة", "ترتيب_محاسبي"]
    for c, h in enumerate(headers, 1):
        ws.cell(3, c, h)
    style_header_row(ws, 3, len(headers))
    set_formula(
        ws["A4"],
        f"IFERROR(SORT({vstack_approved()},5,1,3,1,1,1),\"\")",
    )
    # صافي + رصيد جاري (بعد SORT) — أعمدة AG/AH
    ws["AG3"] = "صافي_الحركة"
    ws["AH3"] = "رصيد_بعد_الحركة"
    style_header_row(ws, 3, 34)
    for r in range(4, 154):
        ws.cell(r, 33).value = (
            f'=IF($E{r}="","",IF($T{r}="نعم",$F{r}-$G{r},0))'
        )
        set_formula(
            ws.cell(r, 34),
            f'IF($E{r}="","",IF(AND($E{r}=$E{r - 1},$AH{r - 1}<>""),'
            f'$AH{r - 1}+$AG{r},XLOOKUP($E{r},tblAccounts[اسم_الحساب],'
            f'tblAccounts[رصيد_افتتاحي],0)+$AG{r}))',
        )


def build_audit(wb: Workbook):
    ws = wb.create_sheet("سجل_التدقيق")
    rtl_sheet(ws)
    ws["A1"] = "كل الحركات المعتمدة + العكس (مرجع أصلي)"
    set_formula(
        ws["A3"],
        'IFERROR(FILTER(tblMove,(tblMove[حالة_الاعتماد]="معتمد")+(tblMove[نوع_الحركة]="عكس")),"")',
    )


def build_approval(wb: Workbook):
    ws = wb.create_sheet("الموافقات")
    rtl_sheet(ws)
    ws["A1"] = "حركات معلقة"
    set_formula(ws["A3"], 'IFERROR(FILTER(tblMove,(tblMove[حالة_الاعتماد]="معلق")),"")')


def build_bank_rec(wb: Workbook):
    ws = wb.create_sheet("مطابقة_البنك")
    rtl_sheet(ws)
    ws["A1"], ws["B1"] = "تاريخ_كشف_البنك", ""
    ws["A2"], ws["B2"] = "رصيد_الكشف", 0
    ws["A4"], ws["B4"] = "رصيد_دفتر_بنك_متاح", (
        '=SUMIFS(tblMove[مدين],tblMove[الحساب],"بنك_متاح",tblMove[حالة_الاعتماد],"معتمد",tblMove[يؤثر_في_الرصيد],"نعم")'
        "-SUMIFS(tblMove[دائن],tblMove[الحساب],\"بنك_متاح\",tblMove[حالة_الاعتماد],\"معتمد\",tblMove[يؤثر_في_الرصيد],\"نعم\")"
        "+SUMIF(tblAccounts[اسم_الحساب],\"بنك_متاح\",tblAccounts[رصيد_افتتاحي])"
    )
    ws["A5"], ws["B5"] = "فرق_المطابقة", "=B2-B4"


def build_cash_count(wb: Workbook):
    ws = wb.create_sheet("جرد_الخزينة")
    rtl_sheet(ws)
    headers = ["التاريخ", "الحساب", "رصيد_الدفتر", "عد_فعلي", "فرق", "سبب", "معتمد"]
    for c, h in enumerate(headers, 1):
        ws.cell(1, c, h)
    style_header_row(ws, 1, len(headers))
    ws["C2"] = (
        '=SUMIFS(tblMove[مدين],tblMove[الحساب],"خزينة نقدية",tblMove[حالة_الاعتماد],"معتمد",tblMove[يؤثر_في_الرصيد],"نعم")'
        '-SUMIFS(tblMove[دائن],tblMove[الحساب],"خزينة نقدية",tblMove[حالة_الاعتماد],"معتمد",tblMove[يؤثر_في_الرصيد],"نعم")'
    )
    ws["E2"] = "=D2-C2"
    add_table(ws, "tblCashCount", "A1:G50")


def build_cashflow(wb: Workbook):
    ws = wb.create_sheet("تحليل_تدفقات_نقدية")
    rtl_sheet(ws)
    ws["A1"] = "تدفقات تشغيلية (يُحسب_في_التدفق_التجاري = نعم فقط)"
    ws["A3"], ws["B3"] = "إجمالي_قبض_تجاري", (
        '=SUMIFS(tblMove[الإجمالي],tblMove[نوع_الحركة],"قبض",tblMove[يُحسب_في_التدفق_التجاري],"نعم",'
        'tblMove[حالة_الاعتماد],"معتمد",tblMove[يؤثر_في_الرصيد],"نعم",tblMove[حالة_السجل],"<>معكوسة")'
    )
    ws["A4"], ws["B4"] = "إجمالي_صرف_تجاري", (
        '=SUMIFS(tblMove[الإجمالي],tblMove[نوع_الحركة],"صرف",tblMove[يُحسب_في_التدفق_التجاري],"نعم",'
        'tblMove[حالة_الاعتماد],"معتمد",tblMove[يؤثر_في_الرصيد],"نعم",tblMove[حالة_السجل],"<>معكوسة")'
    )
    ws["A5"], ws["B5"] = "صافي_تشغيلي", "=B3-B4"


def build_channel(wb: Workbook):
    ws = wb.create_sheet("قبض_حسب_قناة_البيع")
    rtl_sheet(ws)
    ws["A1"] = "قبض تجاري حسب قناة_البيع"
    channels = ["صيدليات", "موزعون", "مستشفيات", "قطاع_حكومي", "أخرى"]
    ws["A3"], ws["B3"] = "القناة", "المبلغ"
    for i, ch in enumerate(channels, 4):
        ws.cell(i, 1, ch)
        ws.cell(i, 2).value = (
            f'=SUMIFS(tblMove[الإجمالي],tblMove[قناة_البيع],"{ch}",tblMove[يُحسب_في_التدفق_التجاري],"نعم",'
            f'tblMove[حالة_الاعتماد],"معتمد",tblMove[نوع_الحركة],"قبض")'
        )


def build_dashboard(wb: Workbook):
    ws = wb.create_sheet("لوحة_التحكم")
    rtl_sheet(ws)
    ws["A1"] = "لوحة التحكم"
    ws["A3"], ws["B3"] = "سيولة_متاحة (افتتاحي+حركة)", (
        '=SUMIFS(tblAccounts[رصيد_افتتاحي],tblAccounts[يُدرج_في_السيولة_المتاحة],"نعم")'
    )
    ws["A4"], ws["B4"] = "شيكات_تحت_التحصيل (وسيط)", (
        '=SUMIFS(tblMove[مدين],tblMove[الحساب],"شيكات_مودعة_تحت_التحصيل",tblMove[يؤثر_في_الرصيد],"نعم")'
        '-SUMIFS(tblMove[دائن],tblMove[الحساب],"شيكات_مودعة_تحت_التحصيل",tblMove[يؤثر_في_الرصيد],"نعم")'
    )
    ws["A6"] = "رصيد_خزينة (من _دفتر_مرتب)"
    set_formula(
        ws["B6"],
        'IFERROR(XLOOKUP("خزينة نقدية",\'_دفتر_مرتب\'!E:E,\'_دفتر_مرتب\'!AH:AH,"",,-1),0)',
    )
    ws["A7"] = "رصيد_بنك_متاح"
    set_formula(
        ws["B7"],
        'IFERROR(XLOOKUP("بنك_متاح",\'_دفتر_مرتب\'!E:E,\'_دفتر_مرتب\'!AH:AH,"",,-1),0)',
    )


def build_monthly(wb: Workbook):
    ws = wb.create_sheet("تقرير_شهري")
    rtl_sheet(ws)
    ws["A1"] = "ملخص شهري (حركات معتمدة — tblMove)"
    ws["A3"], ws["B3"], ws["C3"] = "الشهر", "قبض_تجاري", "صرف_تجاري"
    ws["A4"] = "استخدم Pivot على دفتر_الحركات_المعتمدة أو SUMIFS بالتاريخ"


def add_sample_movement(wb: Workbook):
    ws = wb["حركات_يومية"]
    ws.cell(2, 1, "TRX-00001")
    ws.cell(2, 2, 1)
    ws.cell(2, 3, "2026-01-15")
    ws.cell(2, 4, "قبض")
    ws.cell(2, 5, "شيكات_واردة_بالخزينة")
    ws.cell(2, 8, "REV-SALES")
    ws.cell(2, 9, "VAT_STD")
    ws.cell(2, 10, 10000)
    ws.cell(2, 13, "P001")
    ws.cell(2, 14, "عميل")
    ws.cell(2, 15, "صيدليات")
    ws.cell(2, 20, "نعم")
    ws.cell(2, 21, "نعم")
    ws.cell(2, 22, "معتمد")
    ws.cell(2, 30, "معتمد")
    ws.cell(2, 31, "مثال: استلام شيك — تدفق تجاري")


def protect_workbook(wb: Workbook):
    editable = {
        "README",
        "الإعدادات",
        "قواعد_ضريبية",
        "التصنيفات",
        "دليل_حسابات_مقابل",
        "حسابات_تحليلية",
        "الحسابات",
        "الجهات",
        "حركات_يومية",
        "تحويلات",
        "شيكات",
        "جرد_الخزينة",
    }
    for name in wb.sheetnames:
        ws = wb[name]
        if name.startswith("_"):
            ws.sheet_state = "hidden"
        if name not in editable:
            ws.protection = SheetProtection(sheet=True, objects=False, scenarios=False)


def main():
    wb = Workbook()
    build_readme(wb)
    build_settings(wb)
    build_tax(wb)
    build_categories(wb)
    build_analytical(wb)
    build_accounts(wb)
    build_contra(wb)
    build_parties(wb)
    build_movements(wb)
    build_transfers(wb)
    build_checks(wb)
    build_ledger(wb)
    build_sorted_ledger(wb)
    build_audit(wb)
    build_approval(wb)
    build_bank_rec(wb)
    build_cash_count(wb)
    build_cashflow(wb)
    build_channel(wb)
    build_dashboard(wb)
    build_monthly(wb)
    add_sample_movement(wb)
    protect_workbook(wb)
    wb.calculation = CalcProperties(fullCalcOnLoad=True, calcMode="auto")
    OUT.parent.mkdir(parents=True, exist_ok=True)
    wb.save(OUT)
    print(f"Wrote {OUT}")


if __name__ == "__main__":
    main()
