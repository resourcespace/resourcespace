import os, sys, csv, cv2
import numpy as np

if 1 == len(sys.argv):
    print('Try running: FaceRecognizerTrainer.py <path/to/prepared_data.csv>', file = sys.stderr)
    sys.exit(1)

# Init
PREPARED_DATA_PATH = sys.argv[1]
images             = []
labels             = []

if not os.path.isfile(PREPARED_DATA_PATH):
    print("No file at '{}'".format(PREPARED_DATA_PATH), file = sys.stderr)
    sys.exit(1)

with open(PREPARED_DATA_PATH, 'rt') as csvFile:
    for csvRow in csv.reader(csvFile, delimiter = ';', quotechar = '"'):
        # Expected row should contain: ['/path/to/file', 'label']
        if '' != csvRow[0] and '' != csvRow[1]:
            image = cv2.imread(csvRow[0], 0)

            if image is None:
                print("Could not read image '{}'".format(csvRow[0]))
                continue

            images.append(image)
            labels.append(int(csvRow[1]))

if(0 == len(images)):
    print('No data found for training!', file = sys.stderr)
    sys.exit(1)

# Create an LBPH model for face recognition and train it
# with the images and labels read from the given CSV file
(major, minor, _) = cv2.__version__.split(".")
major = int(major)
minor = int(minor)

if (major<3 and minor<7) or minor<3:
    model = cv2.createLBPHFaceRecognizer(2, 10, 10, 10)
elif major == 4 and minor > 2:
    model = cv2.face.LBPHFaceRecognizer_create(2, 10, 10, 10)
else:
    cv2.face
    model = cv2.face.createLBPHFaceRecognizer(2, 10, 10, 10)


#model = cv2.createLBPHFaceRecognizer(2, 10, 10, 10)
model.train(images, np.array(labels))
model.save("{}/lbph_model.xml".format(os.path.dirname(PREPARED_DATA_PATH)))

print('Successfully trained FaceRecognizer!')