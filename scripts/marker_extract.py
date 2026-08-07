import sys, os, time, json

def main():
    pdf_path = sys.argv[1]
    out_json = sys.argv[2]
    mode = sys.argv[3] if len(sys.argv) > 3 else "fast"
    disable_ocr = (len(sys.argv) > 4 and sys.argv[4] == "1")

    os.environ.setdefault("TORCH_DEVICE", "cpu")

    from marker.converters.pdf import PdfConverter
    from marker.models import create_model_dict
    from marker.config.parser import ConfigParser
    from marker.output import text_from_rendered

    t0 = time.time()
    cfg_dict = {"output_format": "markdown", "mode": mode}
    if disable_ocr:
        cfg_dict["disable_ocr"] = True
    cfg = ConfigParser(cfg_dict)
    converter = PdfConverter(
        config=cfg.generate_config_dict(),
        artifact_dict=create_model_dict(),
        processor_list=cfg.get_processors(),
        renderer=cfg.get_renderer(),
        llm_service=cfg.get_llm_service(),
    )
    rendered = converter(pdf_path)
    text, _, images = text_from_rendered(rendered)

    result = {
        "elapsed_sec": round(time.time() - t0, 1),
        "markdown": text,
    }
    with open(out_json, "w", encoding="utf-8") as f:
        json.dump(result, f, ensure_ascii=False)
    print("OK elapsed=%ss len=%s" % (result["elapsed_sec"], len(text)))

if __name__ == "__main__":
    main()
