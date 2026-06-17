import io

import PySimpleGUI as sg
import qrcode
from PIL import Image


def make_qr(data, box_size=10, border=4):
    qr = qrcode.QRCode(
        version=None,
        error_correction=qrcode.constants.ERROR_CORRECT_M,
        box_size=box_size,
        border=border,
    )
    qr.add_data(data)
    qr.make(fit=True)
    return qr.make_image(fill_color="black", back_color="white")


def make_fixed_size_qr(data, target_px=400):
    img = make_qr(data, box_size=10, border=4)
    img = img.resize((target_px, target_px),
                     Image.NEAREST)  # crisp, not blurry
    return img


def img_to_png_bytes(img):
    buf = io.BytesIO()
    img.save(buf, format="PNG")
    return buf.getvalue()


def main():
    layout = [
        [sg.Text("Info 1"), sg.Input(key="-INFO1-", size=(40, 1))],
        [sg.Text("Info 2"), sg.Input(key="-INFO2-", size=(40, 1))],
        [sg.Button("Generate QR")],
        [sg.Text("Different input lengths, different sizes")],
        [
            sg.Image(key="-QR1-", size=(200, 200)),
            sg.Image(key="-QR2-", size=(200, 200)),
        ],
        [sg.Text("Different input lengths, same size")],
        [
            sg.Image(key="-QR3-", size=(200, 200)),
            sg.Image(key="-QR4-", size=(200, 200)),
        ],
    ]

    window = sg.Window("Same-size QR demo", layout, finalize=True)

    while True:
        event, values = window.read()
        if event in (sg.WIN_CLOSED, "Exit"):
            break

        if event == "Generate QR":
            info1 = values["-INFO1-"]
            info2 = values["-INFO2-"]

            # Row 1: common way, sizes vary with data length
            qr1 = make_qr(info1)
            qr2 = make_qr(info2)

            # Row 2: fixed size regardless of data length
            qr3 = make_fixed_size_qr(info1, target_px=200)
            qr4 = make_fixed_size_qr(info2, target_px=200)

            window["-QR1-"].update(data=img_to_png_bytes(qr1))
            window["-QR2-"].update(data=img_to_png_bytes(qr2))
            window["-QR3-"].update(data=img_to_png_bytes(qr3))
            window["-QR4-"].update(data=img_to_png_bytes(qr4))

    window.close()


if __name__ == "__main__":
    main()
