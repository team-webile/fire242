import cv2
import sys
import os

def detect_exit_poll(image_path):
    # --- Validate image path ---
    if not os.path.exists(image_path):
        print("ERROR: File not found:", image_path)
        return "UNKNOWN"

    # --- Read the image ---
    img = cv2.imread(image_path)
    if img is None:
        print("ERROR: Failed to read image.")
        return "UNKNOWN"

    # --- Convert to grayscale and preprocess ---
    gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)
    blur = cv2.GaussianBlur(gray, (5, 5), 0)

    # --- Adaptive thresholding handles variable lighting better ---
    thresh = cv2.adaptiveThreshold(
        blur, 255, cv2.ADAPTIVE_THRESH_GAUSSIAN_C,
        cv2.THRESH_BINARY_INV, 11, 2
    )

    h, w = thresh.shape

    # --- Define bottom region for "EXIT POLL" ---
    y1, y2 = int(h * 0.85), int(h * 0.95)

    # --- Define approximate X regions for poll options ---
    regions = {
        "FNM": thresh[y1:y2, int(w * 0.15):int(w * 0.25)],
        "PLP": thresh[y1:y2, int(w * 0.30):int(w * 0.40)],
        "COI": thresh[y1:y2, int(w * 0.45):int(w * 0.55)],
        "UNK": thresh[y1:y2, int(w * 0.60):int(w * 0.70)],
    }

    # --- Count white pixels in each region ---
    counts = {name: cv2.countNonZero(region) for name, region in regions.items()}
    print("Pixel counts:", counts)

    # --- Find region with the most white pixels ---
    marked = max(counts, key=counts.get)
    max_count = counts[marked]

    # --- Add threshold to avoid random noise ---
    if max_count < 50:
        print("No clear mark detected.")
        return "UNKNOWN"

    print("Detected poll:", marked)
    return marked

if __name__ == "__main__":
    if len(sys.argv) < 2:
        print("Usage: python detect_exit_poll.py <image_path>")
        sys.exit(1)

    image_path = sys.argv[1]
    result = detect_exit_poll(image_path)
    print(result)
