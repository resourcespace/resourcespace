import os, sys, cv2, json

if 3 > len(sys.argv):
    print('Try running: faceRecognizer.py <path/to/lbph_model.xml> <path/to/image.ext>', file = sys.stderr)
    sys.exit(1)

# Init
LBPH_MODEL_PATH = sys.argv[1]
IMAGE_PATH      = sys.argv[2]

if not os.path.isfile(LBPH_MODEL_PATH):
    print("No model state file '{}'".format(LBPH_MODEL_PATH), file = sys.stderr)
    sys.exit(1)

if not os.path.isfile(IMAGE_PATH):
    print("No test data file at '{}'".format(IMAGE_PATH), file = sys.stderr)
    sys.exit(1)

(major, minor, _) = cv2.__version__.split(".")
major = int(major)
minor = int(minor)

if (major<3 and minor<7) or minor<3:
    model = cv2.createLBPHFaceRecognizer()
    model.load(LBPH_MODEL_PATH)
elif major == 4 and minor > 2:
    model = cv2.face.LBPHFaceRecognizer_create()
    model.read(LBPH_MODEL_PATH)
else:
    model = cv2.face.createLBPHFaceRecognizer()
    model.load(LBPH_MODEL_PATH)
testImage = cv2.imread(IMAGE_PATH, 0)

if testImage is None:
    print("Error: Could not read '{}'".format(IMAGE_PATH), file = sys.stderr)
    sys.exit(1)

predictedData = model.predict(testImage)

print(json.dumps(predictedData))
